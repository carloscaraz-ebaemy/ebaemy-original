<?php

namespace App\Services;

use App\Models\Tenant\FlashSale;

/**
 * EcommerceItemPricing — Precio a mostrar de un producto en el listado.
 *
 * El cálculo estaba escrito dentro de la tarjeta del módulo, y las tarjetas
 * de los 7 themes de nicho —que son forks del markup— nunca lo copiaron: leían
 * `sale_unit_price` a secas. Resultado: un tenant con theme de nicho mostraba
 * el precio de lista aunque tuviera una flash sale o una oferta vigente.
 *
 * Acá vive el cálculo una sola vez. Cada theme sigue teniendo su markup
 * propio —que es el punto de un theme— pero ninguno vuelve a tener su propia
 * idea de cuánto cuesta un producto.
 *
 * Uso desde una tarjeta:
 *
 *     $flash   = EcommerceItemPricing::flashPrices($dataPaginate);
 *     $pricing = EcommerceItemPricing::for($item, $flash);
 *     $pricing->display        // precio a cobrar
 *     $pricing->original       // precio tachado (0 si no hay descuento)
 *     $pricing->hasDiscount
 *     $pricing->discountPct
 */
class EcommerceItemPricing
{
    public float $display;
    public float $original;
    public bool  $hasDiscount;
    public int   $discountPct;
    public string $symbol;

    private function __construct(float $display, float $original, string $symbol)
    {
        $this->display     = $display;
        $this->original    = $original;
        $this->symbol      = $symbol;
        $this->hasDiscount = $original > 0 && $original > $display;
        $this->discountPct = $this->hasDiscount
            ? (int) round((1 - $display / $original) * 100)
            : 0;
    }

    /**
     * Precios de la flash sale activa: [item_id => flash_price].
     *
     * Se resuelve UNA vez por página, no una por producto. El caller la
     * guarda y se la pasa a for() en cada iteración.
     */
    public static function flashPrices(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        try {
            $sale = FlashSale::active()->with('items')->first();
            if ($sale) {
                foreach ($sale->items as $item) {
                    $cache[$item->id] = (float) $item->pivot->flash_price;
                }
            }
        } catch (\Exception $e) {
            // Sin flash sale activa se cae al precio normal.
        }

        return $cache;
    }

    /**
     * Precio efectivo de un producto.
     *
     * Prioridad, de mayor a menor:
     *   1. Flash sale vigente
     *   2. Precio de pack (items marcados como conjunto)
     *   3. compare_at_price vigente — la "oferta tipo Saga": el precio de
     *      venta no cambia, se tacha un precio regular más alto.
     *
     * @param object $item
     * @param array  $flashPrices resultado de flashPrices()
     */
    public static function for($item, array $flashPrices = []): self
    {
        $symbol  = $item->currency_type['symbol'] ?? 'S/';
        $display = (float) $item->sale_unit_price;

        if ($item->is_set && $item->sale_unit_price_set) {
            $display = (float) $item->sale_unit_price_set;
        }

        // 1. Flash sale: solo si realmente baja el precio.
        if (isset($flashPrices[$item->id]) && $flashPrices[$item->id] < $display) {
            return new self($flashPrices[$item->id], $display, $symbol);
        }

        // 2. Pack: el precio de conjunto ya quedó en $display; el tachado es
        //    el precio unitario original cuando es mayor.
        if ($item->is_set && $item->sale_unit_price_set
            && (float) $item->sale_unit_price > $display) {
            return new self($display, (float) $item->sale_unit_price, $symbol);
        }

        // 3. compare_at_price, solo dentro de su ventana de vigencia.
        if ($item->compare_at_price && (float) $item->compare_at_price > $display
            && (empty($item->compare_at_from)  || $item->compare_at_from->startOfDay()->lte(now()))
            && (empty($item->compare_at_until) || $item->compare_at_until->endOfDay()->gte(now()))) {
            return new self($display, (float) $item->compare_at_price, $symbol);
        }

        return new self($display, 0.0, $symbol);
    }

    /** Precio formateado sin símbolo: "1,299.00" */
    public function formatted(): string
    {
        return number_format($this->display, 2);
    }

    /** Precio anterior formateado sin símbolo. */
    public function formattedOriginal(): string
    {
        return number_format($this->original, 2);
    }
}
