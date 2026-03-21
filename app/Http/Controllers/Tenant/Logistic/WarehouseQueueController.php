<?php

namespace App\Http\Controllers\Tenant\Logistic;

use App\Enums\LogisticStatusEnum;
use App\Enums\StockMovementTypeEnum;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\LogisticOrder;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WarehouseQueueController extends Controller
{
    /**
     * Cola de pedidos — usa SaleNote como fuente de verdad.
     * GET /logistic/queue-json
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $query = SaleNote::warehouseQueue()
            ->with(['items.relation_item', 'person'])
            ->when($request->status === 'confirmed',      fn($q) => $q->where('logistic_status', LogisticStatusEnum::PENDIENTE->value))
            ->when($request->status === 'in_preparation', fn($q) => $q->where('logistic_status', LogisticStatusEnum::PREPARANDO->value));

        $orders = $query->paginate($request->per_page ?? 20);

        $data = $orders->getCollection()->map(fn(SaleNote $sn) => $this->formatOrder($sn))->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total'        => $orders->total(),
                'per_page'     => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page'    => $orders->lastPage(),
            ],
            'summary' => [
                'pending_count'  => SaleNote::warehouseQueue()->where('logistic_status', LogisticStatusEnum::PENDIENTE->value)->count(),
                'in_preparation' => SaleNote::warehouseQueue()->where('logistic_status', LogisticStatusEnum::PREPARANDO->value)->count(),
            ],
        ]);
    }

    /**
     * Detalle de un pedido.
     */
    public function show(SaleNote $saleNote): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $saleNote->load(['items.relation_item', 'person']);

        return response()->json([
            'success' => true,
            'data'    => $this->formatOrder($saleNote),
        ]);
    }

    /**
     * Inicia preparación: PENDIENTE → PREPARANDO
     * Guarda el warehouse_id del usuario que inicia el picking.
     */
    public function startPreparation(SaleNote $saleNote): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        if ($saleNote->logistic_status !== LogisticStatusEnum::PENDIENTE) {
            return response()->json(['success' => false, 'message' => 'El pedido no está en estado PENDIENTE.'], 422);
        }

        $user = auth()->user();

        // Buscar el almacén de la sucursal donde se creó el pedido
        $warehouse = Warehouse::where('establishment_id', $saleNote->establishment_id)->first();
        $warehouseId = $warehouse?->id;

        $saleNote->update([
            'logistic_status'   => LogisticStatusEnum::PREPARANDO->value,
            'warehouse_user_id' => $user->id,
            'warehouse_id'      => $warehouseId,
        ]);

        $msg = 'Preparación iniciada.';
        if ($warehouseId) {
            $msg .= " Almacén: {$warehouse->description}";
        } else {
            $msg .= ' (La sucursal no tiene almacén asignado)';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'data'    => $this->formatOrder($saleNote->fresh(['items.relation_item', 'person'])),
        ]);
    }

    /**
     * Cancela un pedido → vuelve a PENDIENTE.
     */
    public function cancel(Request $request, SaleNote $saleNote): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        DB::transaction(function () use ($saleNote) {
            // Liberar stock comprometido. Si no hay warehouse_id explícito (flujo form-based),
            // caer al almacén del establecimiento (donde se hizo PROVINCE_COMMIT al crear la NV).
            $warehouseId = $saleNote->warehouse_id
                ?? Warehouse::where('establishment_id', $saleNote->establishment_id)->value('id');

            if ($warehouseId) {
                $saleNote->load('items');
                foreach ($saleNote->items as $item) {
                    $iw = ItemWarehouse::where('item_id',     $item->item_id)
                                       ->where('warehouse_id', $warehouseId)
                                       ->lockForUpdate()
                                       ->first();

                    if ($iw) {
                        $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_CANCEL, (float) $item->quantity);
                        StockMovement::record(
                            $iw,
                            StockMovementTypeEnum::PROVINCE_CANCEL,
                            (float) $item->quantity,
                            auth()->id(),
                            $saleNote,
                            "Cancelación NV #{$saleNote->number_full}"
                        );
                    }
                }
            }

            $saleNote->update([
                'logistic_status' => LogisticStatusEnum::PENDIENTE->value,
                'warehouse_id'    => null,
                'warehouse_user_id' => null,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Pedido devuelto a cola y stock liberado.',
            'data'    => $this->formatOrder($saleNote->fresh(['items.relation_item', 'person'])),
        ]);
    }

    /**
     * Completa datos de envío faltantes desde la cola del almacén.
     * PATCH /logistic/queue-json/{saleNote}/update-shipping
     */
    public function updateShipping(Request $request, SaleNote $saleNote): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $validated = $request->validate([
            'shipping_recipient'   => 'required|string|max:200',
            'shipping_phone'       => 'nullable|string|max:20',
            'shipping_address'     => 'required|string|max:300',
            'shipping_city'        => 'nullable|string|max:100',
            'shipping_district_id' => 'nullable|string|max:6',
            'shipping_notes'       => 'nullable|string|max:500',
        ]);

        $saleNote->update($validated);

        $saleNote->load(['items.relation_item', 'person']);

        return response()->json([
            'success' => true,
            'message' => 'Datos de envío actualizados.',
            'data'    => $this->formatOrder($saleNote),
        ]);
    }

    /**
     * Marca el pedido como listo para despacho: PREPARANDO → LISTO_DESPACHO
     * POST /logistic/queue-json/{saleNote}/ready
     */
    public function markReady(SaleNote $saleNote): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        if ($saleNote->logistic_status !== LogisticStatusEnum::PREPARANDO) {
            return response()->json(['success' => false, 'message' => 'El pedido no está en estado PREPARANDO.'], 422);
        }

        $saleNote->update([
            'logistic_status' => LogisticStatusEnum::LISTO_DESPACHO->value,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pedido marcado como Listo para Despacho.',
            'data'    => $this->formatOrder($saleNote->fresh(['items.relation_item', 'person'])),
        ]);
    }

    /**
     * Despacha el pedido: LISTO_DESPACHO → DESPACHADO
     * POST /logistic/queue-json/{saleNote}/dispatch
     */
    public function dispatchOrder(Request $request, SaleNote $saleNote): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        if ($saleNote->logistic_status !== LogisticStatusEnum::LISTO_DESPACHO) {
            return response()->json(['success' => false, 'message' => 'El pedido debe estar en estado LISTO PARA DESPACHO.'], 422);
        }

        $validated = $request->validate([
            'courier_name'    => 'nullable|string|max:150',
            'tracking_number' => 'nullable|string|max:100',
            'notes'           => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($saleNote, $validated) {
            // 1. Actualizar estado del pedido
            $saleNote->update([
                'logistic_status'  => LogisticStatusEnum::DESPACHADO->value,
                'courier_name'     => $validated['courier_name']    ?? $saleNote->courier_name,
                'tracking_number'  => $validated['tracking_number'] ?? $saleNote->tracking_number,
                'dispatch_date'    => now(),
                'warehouse_notes'  => $validated['notes']           ?? null,
            ]);

            // 2. Descontar stock del almacén de la sucursal
            $warehouseId = $saleNote->warehouse_id;
            if (!$warehouseId) {
                $warehouse   = Warehouse::where('establishment_id', $saleNote->establishment_id)->first();
                $warehouseId = $warehouse?->id;
            }

            if ($warehouseId) {
                $saleNote->load('items');
                foreach ($saleNote->items as $item) {
                    $iw = ItemWarehouse::where('item_id',     $item->item_id)
                                       ->where('warehouse_id', $warehouseId)
                                       ->lockForUpdate()
                                       ->first();

                    if ($iw) {
                        $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_DISPATCH, (float) $item->quantity);
                        StockMovement::record(
                            $iw,
                            StockMovementTypeEnum::PROVINCE_DISPATCH,
                            (float) $item->quantity,
                            auth()->id(),
                            $saleNote,
                            "Despacho NV #{$saleNote->number_full}"
                        );
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Pedido despachado y stock descontado correctamente.',
            'data'    => $this->formatOrder($saleNote->fresh(['items.relation_item', 'person'])),
        ]);
    }

    /**
     * Movimientos de stock de un pedido.
     * GET /logistic/queue-json/{saleNote}/stock-movements
     */
    public function stockMovements(SaleNote $saleNote): JsonResponse
    {
        $this->authorize('viewWarehouseQueue', LogisticOrder::class);

        $movements = StockMovement::where('reference_type', SaleNote::class)
            ->where('reference_id', $saleNote->id)
            ->with('item:id,description')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn(StockMovement $m) => [
                'id'                    => $m->id,
                'type'                  => $m->type->value,
                'type_label'            => $m->type->label(),
                'item_description'      => optional($m->item)->description ?? "Ítem #{$m->item_id}",
                'qty_physical'          => $m->qty_physical,
                'qty_committed'         => $m->qty_committed,
                'stock_physical_after'  => $m->stock_physical_after,
                'stock_committed_after' => $m->stock_committed_after,
                'stock_available_after' => $m->stock_available_after,
                'notes'                 => $m->notes,
                'created_at'            => $m->created_at?->format('d/m/Y H:i:s'),
            ]);

        return response()->json(['success' => true, 'data' => $movements]);
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function formatOrder(SaleNote $sn): array
    {
        $status = $sn->logistic_status;

        $statusLabel = $status?->label() ?? '—';
        $badgeColor  = $status?->badgeColor() ?? 'secondary';

        $statusKey = match($status) {
            LogisticStatusEnum::PENDIENTE      => 'confirmed',
            LogisticStatusEnum::PREPARANDO     => 'in_preparation',
            LogisticStatusEnum::LISTO_DESPACHO => 'ready',
            LogisticStatusEnum::DESPACHADO     => 'dispatched',
            default                            => 'unknown',
        };

        $clientName   = optional($sn->person)->name ?? '—';
        $clientNumber = optional($sn->person)->number ?? '';

        // Detectar si le faltan datos de envío
        $missingShipping = empty($sn->shipping_recipient) || empty($sn->shipping_address);

        return [
            'id'                     => $sn->id,
            'number_full'            => $sn->number_full,
            'status'                 => $statusKey,
            'status_label'           => $statusLabel,
            'badge_color'            => $badgeColor,
            'is_urgent'              => (bool) $sn->is_urgent,
            'source'                 => 'pos',
            'missing_shipping'       => $missingShipping,   // ← flag para el Vue
            // Cliente
            'customer'               => ['name' => $clientName, 'number' => $clientNumber],
            // Destinatario / envío
            'shipping_recipient'     => $sn->shipping_recipient ?? '',
            'shipping_phone'         => $sn->shipping_phone ?? '',
            'shipping_address'       => $sn->shipping_address ?? '',
            'shipping_city'          => $sn->shipping_city ?? '',
            'shipping_notes'         => $sn->shipping_notes ?? '',
            'recipient_name'         => $sn->shipping_recipient ?? $clientName,
            'recipient_phone'        => $sn->shipping_phone ?? optional($sn->person)->telephone ?? '',
            'destination_address'    => $sn->shipping_address ?: '—',
            'destination_district'   => $sn->shipping_city ?? '',
            // Almacén asignado
            'warehouse_id'           => $sn->warehouse_id,
            // Totales
            'total'                  => $sn->total,
            'currency_type_id'       => $sn->currency_type_id ?? 'PEN',
            // Fechas
            'created_at'             => $sn->created_at?->format('d/m/Y H:i'),
            'confirmed_at'           => $sn->created_at?->format('d/m/Y H:i'),
            // Courier
            'preferred_courier'      => $sn->preferred_courier ?? '',
            'shipping_cost_customer' => $sn->shipping_cost_customer ?? 0,
            // Ítems
            'items'                  => $sn->items->map(fn($i) => [
                'item_id'     => $i->item_id,
                'description' => optional($i->relation_item)->description ?? $i->description ?? '—',
                'quantity'    => $i->quantity,
                'unit_price'  => $i->unit_price,
                'total'       => round((float)$i->quantity * (float)$i->unit_price, 2),
            ])->toArray(),
        ];
    }
}
