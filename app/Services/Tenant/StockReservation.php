<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemSet;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\ItemVariantWarehouse;
use App\Models\Tenant\ItemWarehouse;
use Illuminate\Support\Facades\DB;

/**
 * Validar y reservar el stock de las lineas de un pedido.
 *
 * ── Por que existe ────────────────────────────────────────────────────────
 *
 * `OrderController::storeManual()` creaba pedidos SIN mirar el stock: cero
 * comprobaciones y cero reservas. Un operador podia vender por telefono lo que
 * el ecommerce acababa de vender, y nadie se enteraba hasta preparar el pedido.
 *
 * La logica correcta ya existia, pero enterrada dentro del checkout del
 * ecommerce (`EcommerceController`), que es el unico sitio del sistema que
 * maneja variantes, packs y reserva con bloqueo. Se extrae aqui para que el
 * alta manual la use tal cual en vez de copiarla.
 *
 * ── Estado de la unificacion ──────────────────────────────────────────────
 *
 * Hoy este servicio tiene UN consumidor: el alta manual. El checkout sigue con
 * su copia. No se migro en el mismo paso a proposito: es el camino de venta en
 * vivo y tocarlo pide su propia verificacion. **Migrarlo es el siguiente paso**,
 * y hasta entonces esto es una segunda implementacion — se dice aqui para que
 * quien lo lea no crea que ya esta unificado.
 *
 * ── Las tres formas de una linea ──────────────────────────────────────────
 *
 *   variante  el stock vive en la variante, no en el producto padre
 *   pack      no tiene stock propio: se mira el de cada componente
 *   simple    stock del almacen del canal, o el que mas tenga
 *
 * Siempre `stock_available` (fisico menos comprometido) y nunca la columna
 * `stock` heredada: ver el sistema DUAL de stock.
 */
class StockReservation
{
    /**
     * Que impide vender estas lineas. Vacio = se puede.
     *
     * Devuelve mensajes para el operador, no codigos: quien llama los muestra
     * tal cual. Se comprueban TODAS las lineas y no se corta en la primera,
     * porque avisar de un problema por intento es la forma mas lenta de
     * corregir un pedido de ocho productos.
     *
     * @param array<int, array{item_id:int, variant_id?:int|null, quantity:float}> $lineas
     * @return array<int, string>
     */
    public function problemas(array $lineas, ?int $warehouseId = null): array
    {
        $problemas = [];

        foreach ($lineas as $linea) {
            $item = Item::find($linea['item_id'] ?? null);
            if (!$item) {
                $problemas[] = 'Un producto del pedido ya no existe en el catálogo.';
                continue;
            }

            $cantidad  = (float) ($linea['quantity'] ?? 0);
            $variantId = $linea['variant_id'] ?? null;

            if ($cantidad <= 0) {
                $problemas[] = "Indica una cantidad mayor que cero para «{$item->description}».";
                continue;
            }

            if ($variantId) {
                $problemas = array_merge($problemas, $this->problemasDeVariante($item, (int) $variantId, $cantidad));
                continue;
            }

            if ($item->is_set) {
                $problemas = array_merge($problemas, $this->problemasDePack($item, $cantidad, $warehouseId));
                continue;
            }

            $disponible = $this->disponibleDeItem($item->id, $warehouseId);

            // `null` = el producto no tiene fila de almacen. No se inventa un
            // cero: hay catalogos sin control de stock y bloquear ahi seria
            // impedir vender algo que si esta.
            if ($disponible !== null && $disponible < $cantidad) {
                $problemas[] = "Stock insuficiente de «{$item->description}». Disponible: "
                    . $this->numero($disponible) . ', pedido: ' . $this->numero($cantidad) . '.';
            }
        }

        return $problemas;
    }

    /**
     * Compromete el stock de las lineas.
     *
     * Cada reserva va en su propia transaccion con `lockForUpdate`, igual que
     * en el checkout: dos altas simultaneas del mismo producto no pueden leer
     * el mismo `stock_committed` y pisarse.
     *
     * NO revalida: quien llama debe haber pasado por `problemas()` antes. Entre
     * las dos hay una ventana, y por eso el bloqueo es sobre la fila.
     *
     * @param array<int, array{item_id:int, variant_id?:int|null, quantity:float}> $lineas
     */
    public function reservar(array $lineas, ?int $warehouseId = null): void
    {
        foreach ($lineas as $linea) {
            $cantidad  = (float) ($linea['quantity'] ?? 0);
            $itemId    = $linea['item_id'] ?? null;
            $variantId = $linea['variant_id'] ?? null;

            if ($cantidad <= 0 || !$itemId) {
                continue;
            }

            if ($variantId) {
                $this->comprometerVariante((int) $variantId, $cantidad);
                continue;
            }

            $item = Item::find($itemId);
            if (!$item) {
                continue;
            }

            if ($item->is_set) {
                foreach (ItemSet::where('item_id', $item->id)->get() as $componente) {
                    $necesita = $cantidad * (float) $componente->quantity;
                    if ($necesita > 0) {
                        $this->comprometerItem((int) $componente->individual_item_id, $necesita, $warehouseId);
                    }
                }
                continue;
            }

            $this->comprometerItem((int) $item->id, $cantidad, $warehouseId);
        }
    }

    // ── Validacion por forma ──────────────────────────────────────────────

    /** @return array<int, string> */
    private function problemasDeVariante(Item $item, int $variantId, float $cantidad): array
    {
        $variante = ItemVariant::find($variantId);

        if (!$variante || !$variante->is_active) {
            return ['La variante elegida de «' . $item->description . '» ya no está disponible.'];
        }

        // El stock de la variante es su total menos lo ya comprometido en
        // TODOS los almacenes: la variante no se reparte por canal.
        $comprometido = (float) ItemVariantWarehouse::where('item_variant_id', $variantId)->sum('stock_committed');
        $disponible   = max(0, (float) $variante->stock - $comprometido);

        if ($disponible < $cantidad) {
            $nombre = $variante->display_name ?: $item->description;

            return ["Stock insuficiente de «{$nombre}». Disponible: "
                . $this->numero($disponible) . ', pedido: ' . $this->numero($cantidad) . '.'];
        }

        return [];
    }

    /** @return array<int, string> */
    private function problemasDePack(Item $pack, float $cantidad, ?int $warehouseId): array
    {
        $problemas = [];

        foreach (ItemSet::where('item_id', $pack->id)->with('individual_item')->get() as $componente) {
            if (!$componente->individual_item) {
                continue;
            }

            $necesita   = $cantidad * (float) $componente->quantity;
            $disponible = $this->disponibleDeItem((int) $componente->individual_item_id, $warehouseId);

            if ($disponible !== null && $disponible < $necesita) {
                $problemas[] = 'Stock insuficiente del componente «'
                    . $componente->individual_item->description . '» del pack «' . $pack->description
                    . '». Necesario: ' . $this->numero($necesita)
                    . ', disponible: ' . $this->numero($disponible) . '.';
            }
        }

        return $problemas;
    }

    // ── Acceso al almacen ─────────────────────────────────────────────────

    /** Disponible de un producto simple, o null si no lleva control de stock. */
    private function disponibleDeItem(int $itemId, ?int $warehouseId): ?float
    {
        $fila = $this->filaDeAlmacen($itemId, $warehouseId);

        return $fila ? (float) $fila->stock_available : null;
    }

    private function filaDeAlmacen(int $itemId, ?int $warehouseId, bool $bloquear = false)
    {
        $q = ItemWarehouse::where('item_id', $itemId);

        // Sin almacen de canal se toma el que mas tenga: es lo que hace el
        // checkout, y evita reservar contra un almacen vacio teniendo stock.
        $q = $warehouseId
            ? $q->where('warehouse_id', $warehouseId)
            : $q->orderByDesc('stock_physical');

        return $bloquear ? $q->lockForUpdate()->first() : $q->first();
    }

    private function comprometerItem(int $itemId, float $cantidad, ?int $warehouseId): void
    {
        DB::connection('tenant')->transaction(function () use ($itemId, $cantidad, $warehouseId) {
            $fila = $this->filaDeAlmacen($itemId, $warehouseId, true);

            if ($fila) {
                $fila->stock_committed = (float) $fila->stock_committed + $cantidad;
                $fila->save();
            }
        });
    }

    private function comprometerVariante(int $variantId, float $cantidad): void
    {
        DB::connection('tenant')->transaction(function () use ($variantId, $cantidad) {
            $fila = ItemVariantWarehouse::where('item_variant_id', $variantId)
                ->orderByDesc('stock_physical')
                ->lockForUpdate()
                ->first();

            if ($fila) {
                $fila->stock_committed = (float) $fila->stock_committed + $cantidad;
                $fila->save();
            }
        });
    }

    /** Cantidades legibles: 3 y no 3.00, pero 2.5 cuando lo es. */
    private function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
