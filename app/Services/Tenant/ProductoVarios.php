<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Item;
use Illuminate\Support\Facades\DB;

/**
 * El producto comodín de la tienda para lo que no está en el catálogo.
 *
 * Las ventas del enlace de registro traen los productos escritos a mano en el
 * envío («01 cebra x 24 hojas»). Guía, nota de venta y comprobante exigen un
 * producto con código, así que esas líneas viajan como este producto con el
 * texto del pedido como descripción: lo que sale impreso es el texto, no
 * «PRODUCTOS VARIOS».
 *
 * No todas las tiendas lo tienen ni lo llaman igual: alasitas «PRODUCTOS
 * VARIOS», calixto y talara «producto variado» (código XX-100). El
 * `VARIOUS_ITEM` / `REPLACE DESCRIPTION` es la convención heredada del ERP.
 */
class ProductoVarios
{
    public const NOMBRES = ['productos varios', 'producto variado', 'productos variados', 'varios', 'producto varios'];

    public static function buscar(): ?Item
    {
        try {
            return Item::query()
                ->where(function ($q) {
                    $q->where('internal_id', 'XX-100')
                      ->orWhere('barcode', 'VARIOUS_ITEM')
                      ->orWhereIn(DB::raw('LOWER(TRIM(description))'), self::NOMBRES);
                })
                ->where('active', true)
                ->orderByRaw("internal_id = 'XX-100' DESC")
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** ¿La descripción de la línea manda sobre la del producto? */
    public static function es(?Item $item): bool
    {
        if (!$item) {
            return false;
        }

        return $item->barcode === 'VARIOUS_ITEM'
            || $item->description === 'REPLACE DESCRIPTION'
            || $item->internal_id === 'XX-100'
            || in_array(mb_strtolower(trim((string) $item->description)), self::NOMBRES, true);
    }
}
