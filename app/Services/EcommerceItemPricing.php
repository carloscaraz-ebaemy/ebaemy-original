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

    /**
     * Rango de precios de las variantes. Cuando el producto tiene variantes con
     * precios distintos, la tarjeta debe decir «desde S/ 300» y no un precio
     * exacto que cambia al entrar en la ficha.
     *
     * null en los dos cuando el producto no tiene variantes, o cuando todas
     * valen lo mismo y por tanto no hay rango del que hablar.
     */
    public ?float $priceMin = null;
    public ?float $priceMax = null;

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

    /** ¿Hay que decir «desde»? Solo si las variantes no valen todas lo mismo. */
    public function isRange(): bool
    {
        return $this->priceMin !== null
            && $this->priceMax !== null
            && $this->priceMax > $this->priceMin;
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
     * Rango de precios de las variantes: [item_id => ['min' => x, 'max' => y]].
     *
     * Se resuelve UNA vez por request para todos los productos de la página, no
     * uno por producto: el listado es la pantalla más cargada del ecommerce.
     *
     * El COALESCE contra items.sale_unit_price es obligatorio — una variante con
     * precio null hereda el del padre, y sin él el mínimo saldría 0 en cuanto
     * una talla no tuviera precio propio, y la tarjeta anunciaría «desde S/ 0».
     */
    public static function variantRanges(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        try {
            $rows = \Illuminate\Support\Facades\DB::connection('tenant')
                ->table('item_variants as iv')
                ->join('items as i', 'i.id', '=', 'iv.item_id')
                ->where('iv.is_active', 1)
                ->groupBy('iv.item_id')
                ->selectRaw('iv.item_id,
                             MIN(COALESCE(iv.sale_unit_price, i.sale_unit_price)) AS min_p,
                             MAX(COALESCE(iv.sale_unit_price, i.sale_unit_price)) AS max_p')
                ->get();

            foreach ($rows as $row) {
                $cache[(int) $row->item_id] = [
                    'min' => (float) $row->min_p,
                    'max' => (float) $row->max_p,
                ];
            }
        } catch (\Throwable $e) {
            // Tenant sin las tablas de variantes: se comporta como antes.
            $cache = [];
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
     * Para productos con variantes, el precio base es el MÍNIMO de sus variantes
     * activas. Antes se leía `sale_unit_price` del padre a secas: un producto
     * cuyas tallas van de 300 a 320 se listaba al precio del padre —que puede ser
     * cualquier cosa— y el precio cambiaba al entrar a la ficha. El marketplace
     * central sí resolvía el rango; la tienda propia del tenant, no.
     *
     * @param object $item
     * @param array  $flashPrices resultado de flashPrices()
     * @param array  $variantRanges resultado de variantRanges()
     */
    public static function for($item, array $flashPrices = [], array $variantRanges = []): self
    {
        $symbol  = $item->currency_type['symbol'] ?? 'S/';
        $display = (float) $item->sale_unit_price;

        // Rango de variantes. Se aplica antes que todo lo demás porque cambia el
        // precio base sobre el que operan el pack y compare_at_price.
        $range = null;
        if (!empty($item->has_variants)) {
            $range = $variantRanges[$item->id] ?? (self::variantRanges()[$item->id] ?? null);
            if ($range && $range['min'] > 0) {
                $display = $range['min'];
            }
        }

        if ($item->is_set && $item->sale_unit_price_set) {
            $display = (float) $item->sale_unit_price_set;
        }

        // El rango viaja en el objeto resultante sea cual sea la rama que gane,
        // para que la tarjeta pueda escribir «desde» sin volver a consultar.
        $withRange = function (self $pricing) use ($range): self {
            if ($range && $range['min'] > 0) {
                $pricing->priceMin = $range['min'];
                $pricing->priceMax = $range['max'];
            }
            return $pricing;
        };

        // 1. Flash sale: solo si realmente baja el precio.
        if (isset($flashPrices[$item->id]) && $flashPrices[$item->id] < $display) {
            return $withRange(new self($flashPrices[$item->id], $display, $symbol));
        }

        // 2. Pack: el precio de conjunto ya quedó en $display; el tachado es
        //    el precio unitario original cuando es mayor.
        if ($item->is_set && $item->sale_unit_price_set
            && (float) $item->sale_unit_price > $display) {
            return $withRange(new self($display, (float) $item->sale_unit_price, $symbol));
        }

        // 3. compare_at_price, solo dentro de su ventana de vigencia.
        if ($item->compare_at_price && (float) $item->compare_at_price > $display
            && (empty($item->compare_at_from)  || $item->compare_at_from->startOfDay()->lte(now()))
            && (empty($item->compare_at_until) || $item->compare_at_until->endOfDay()->gte(now()))) {
            return $withRange(new self($display, (float) $item->compare_at_price, $symbol));
        }

        return $withRange(new self($display, 0.0, $symbol));
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
