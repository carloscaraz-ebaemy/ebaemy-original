<?php

namespace App\Services\Tenant;

use App\Enums\StockMovementTypeEnum;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\StockMovement;
use App\Traits\InventoryKardexTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SaleNoteStockService
 *
 * Centraliza toda la lógica de movimiento de stock para Notas de Venta.
 * Reemplaza progresivamente la lógica embebida en InventoryKardexServiceProvider.
 *
 * FLUJOS:
 *   TIENDA   (requires_warehouse_dispatch = false)
 *     created → SALE_STORE      (-physical)  + inventory_kardex (legacy)
 *     deleted → SALE_STORE_RETURN (+physical)
 *
 *   PROVINCE (requires_warehouse_dispatch = true)
 *     created → PROVINCE_COMMIT  (+committed) — StockMovement únicamente
 *     deleted → PROVINCE_CANCEL  (-committed)
 *
 * REGLAS DE SEGURIDAD:
 *   - Siempre dentro de DB::transaction() con lockForUpdate()
 *   - Fallback legacy si ItemWarehouse no existe (compatible con empresas sin smart stock)
 *   - Los errores de stock NO deben interrumpir la creación de la NV → catch + log
 */
class SaleNoteStockService
{
    use InventoryKardexTrait;

    /**
     * Aplica el movimiento de stock cuando se crea un ítem de NV.
     * Punto de entrada desde InventoryKardexServiceProvider.
     */
    public function onItemCreated(SaleNoteItem $item): void
    {
        $saleNote  = $item->sale_note;
        $qty       = (float) $item->quantity;
        $estId     = $saleNote->establishment_id;
        $warehouseId = $item->warehouse_id ?? $this->getWarehouseId($estId);

        if (!$warehouseId) {
            Log::warning('[SaleNoteStock] Sin warehouse resuelto para ítem', [
                'sale_note_item_id' => $item->id,
                'item_id'           => $item->item_id,
                'establishment_id'  => $estId,
            ]);
            return;
        }

        if ($saleNote->requires_warehouse_dispatch) {
            $this->commitProvince($item, $saleNote, $warehouseId, $qty);
        } else {
            $this->deductStore($item, $saleNote, $warehouseId, $qty, $estId);
        }
    }

    /**
     * Revierte el movimiento de stock cuando se elimina un ítem de NV (durante edición).
     * Punto de entrada desde InventoryKardexServiceProvider.
     */
    public function onItemDeleted(SaleNoteItem $item): void
    {
        $saleNote    = $item->sale_note;
        $qty         = (float) $item->quantity;
        $estId       = $saleNote->establishment_id;
        $warehouseId = $item->warehouse_id ?? $this->getWarehouseId($estId);

        if ($saleNote->requires_warehouse_dispatch) {
            if (!$warehouseId) return;
            $this->cancelProvince($item, $saleNote, $warehouseId, $qty);
        } else {
            $this->revertStore($item, $saleNote, $warehouseId, $qty, $estId);
        }
    }

    // ─── Lógica privada ──────────────────────────────────────────────────────

    /**
     * PROVINCE: reserva stock_committed (no toca stock_physical).
     */
    private function commitProvince(
        SaleNoteItem $item,
        SaleNote     $saleNote,
        int          $warehouseId,
        float        $qty
    ): void {
        DB::transaction(function () use ($item, $saleNote, $warehouseId, $qty) {
            $iw = $this->lockItemWarehouse($item->item_id, $warehouseId);
            if (!$iw) return;

            $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_COMMIT, $qty);

            StockMovement::record(
                $iw,
                StockMovementTypeEnum::PROVINCE_COMMIT,
                $qty,
                auth()->id(),
                $saleNote,
                "Reserva NV #{$saleNote->number_full}"
            );
        });
    }

    /**
     * PROVINCE: libera stock_committed al eliminar un ítem.
     */
    private function cancelProvince(
        SaleNoteItem $item,
        SaleNote     $saleNote,
        int          $warehouseId,
        float        $qty
    ): void {
        DB::transaction(function () use ($item, $saleNote, $warehouseId, $qty) {
            $iw = $this->lockItemWarehouse($item->item_id, $warehouseId);
            if (!$iw) return;

            $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_CANCEL, $qty);

            StockMovement::record(
                $iw,
                StockMovementTypeEnum::PROVINCE_CANCEL,
                $qty,
                auth()->id(),
                $saleNote,
                "Reversión ítem NV #{$saleNote->number_full}"
            );
        });
    }

    /**
     * TIENDA: descuenta stock_physical + escribe inventory_kardex (legacy) + StockMovement.
     */
    private function deductStore(
        SaleNoteItem $item,
        SaleNote     $saleNote,
        int          $warehouseId,
        float        $qty,
        int          $estId
    ): void {
        DB::transaction(function () use ($item, $saleNote, $warehouseId, $qty, $estId) {
            // Kardex legacy dentro de la transaction — si falla el stock, no queda el kardex huérfano
            $this->saveInventoryKardex($saleNote, $item->item_id, $estId, $qty);

            $iw = $this->lockItemWarehouse($item->item_id, $warehouseId);

            if (!$iw) {
                // Fallback legacy para ítems sin ItemWarehouse configurado
                $this->updateStock($item->item_id, $estId, $qty, true, $warehouseId);
                return;
            }

            $iw->applyStockMovement(StockMovementTypeEnum::SALE_STORE, $qty);

            StockMovement::record(
                $iw,
                StockMovementTypeEnum::SALE_STORE,
                $qty,
                auth()->id(),
                $saleNote,
                "Venta tienda NV #{$saleNote->number_full}"
            );
        });
    }

    /**
     * TIENDA: devuelve stock_physical al eliminar un ítem.
     */
    private function revertStore(
        SaleNoteItem $item,
        SaleNote     $saleNote,
        ?int         $warehouseId,
        float        $qty,
        int          $estId
    ): void {
        if (!$warehouseId) {
            $this->updateStock($item->item_id, $estId, $qty, false);
            return;
        }

        DB::transaction(function () use ($item, $saleNote, $warehouseId, $qty, $estId) {
            $iw = $this->lockItemWarehouse($item->item_id, $warehouseId);

            if (!$iw) {
                $this->updateStock($item->item_id, $estId, $qty, false);
                return;
            }

            $iw->applyStockMovement(StockMovementTypeEnum::SALE_STORE_RETURN, $qty);

            StockMovement::record(
                $iw,
                StockMovementTypeEnum::SALE_STORE_RETURN,
                $qty,
                auth()->id(),
                $saleNote,
                "Reversión venta tienda NV #{$saleNote->number_full}"
            );
        });
    }

    /**
     * Obtiene ItemWarehouse con lock pesimista (para usar dentro de transaction).
     */
    private function lockItemWarehouse(int $itemId, int $warehouseId): ?ItemWarehouse
    {
        return ItemWarehouse::where('item_id', $itemId)
                            ->where('warehouse_id', $warehouseId)
                            ->lockForUpdate()
                            ->first();
    }
}
