<?php

namespace App\Services\Tenant\Pricing;

/**
 * Calculadora de precios — clase PURA sin dependencias de Laravel.
 *
 * Fórmula canónica: MARGIN sobre venta (no markup).
 *   list_price  = effective_cost / (1 - target_margin_pct/100)
 *   floor_price = effective_cost / (1 - min_margin_pct/100)
 *   margin_pct  = (price - cost) / price * 100
 *
 * Ejemplo: cost=100, margin=50% → list=200 (no 150 como markup).
 *
 * Todo método retorna float redondeado a 4 decimales (compatible con
 * items.sale_unit_price decimal(16,6)). Para mostrar al usuario, formatear
 * a 2 decimales en la capa de presentación.
 */
class PriceCalculator
{
    public const PRECISION = 4;

    /**
     * Costo efectivo = costo base + % adicional (flete, importación, mermas).
     */
    public static function effectiveCost(float $costUnit, float $landedCostExtraPct = 0): float
    {
        if ($costUnit < 0) {
            throw new \InvalidArgumentException('cost_unit no puede ser negativo');
        }
        return round($costUnit * (1 + $landedCostExtraPct / 100), self::PRECISION);
    }

    /**
     * Precio sugerido por margen objetivo (fórmula margin sobre venta).
     * Si target_margin_pct >= 100, lanza excepción (división por cero/negativo).
     */
    public static function listPriceFromMargin(float $effectiveCost, float $targetMarginPct): float
    {
        if ($targetMarginPct >= 100) {
            throw new \InvalidArgumentException('target_margin_pct debe ser < 100');
        }
        if ($targetMarginPct < 0) {
            throw new \InvalidArgumentException('target_margin_pct no puede ser negativo');
        }
        return round($effectiveCost / (1 - $targetMarginPct / 100), self::PRECISION);
    }

    /**
     * Precio piso por margen mínimo (mismo cálculo que listPriceFromMargin pero
     * con minMarginPct). Si minMargin=0 → floor=cost (vender al costo permitido).
     */
    public static function floorPrice(float $effectiveCost, float $minMarginPct): float
    {
        if ($minMarginPct >= 100) {
            throw new \InvalidArgumentException('min_margin_pct debe ser < 100');
        }
        if ($minMarginPct <= 0) {
            return $effectiveCost;
        }
        return round($effectiveCost / (1 - $minMarginPct / 100), self::PRECISION);
    }

    /**
     * Precio final tras aplicar descuento porcentual.
     */
    public static function finalPrice(float $salePrice, float $discountPct = 0): float
    {
        if ($discountPct < 0 || $discountPct > 100) {
            throw new \InvalidArgumentException('discount_pct debe estar entre 0 y 100');
        }
        return round($salePrice * (1 - $discountPct / 100), self::PRECISION);
    }

    /**
     * Margen real (%) de un precio dado contra el costo efectivo.
     * Fórmula: (price - cost) / price * 100 — sobre venta.
     * Retorna negativo si hay pérdida.
     */
    public static function marginPct(float $price, float $effectiveCost): float
    {
        if ($price <= 0) {
            return 0.0;
        }
        return round(($price - $effectiveCost) / $price * 100, 2);
    }

    /**
     * Markup real (%) — alternativo, sobre costo. Solo para mostrar como info.
     */
    public static function markupPct(float $price, float $effectiveCost): float
    {
        if ($effectiveCost <= 0) {
            return 0.0;
        }
        return round(($price - $effectiveCost) / $effectiveCost * 100, 2);
    }

    /**
     * Utilidad por unidad (puede ser negativa).
     */
    public static function profitPerUnit(float $price, float $effectiveCost): float
    {
        return round($price - $effectiveCost, self::PRECISION);
    }

    /**
     * Ahorro mostrado al cliente: diferencia entre el precio tachado y el final.
     * Si no hay compareAtPrice o es menor al final, retorna 0.
     */
    public static function customerSavings(float $finalPrice, ?float $compareAtPrice): float
    {
        if ($compareAtPrice === null || $compareAtPrice <= $finalPrice) {
            return 0.0;
        }
        return round($compareAtPrice - $finalPrice, self::PRECISION);
    }

    /**
     * Porcentaje de descuento visible al cliente: (compareAt - final) / compareAt * 100.
     */
    public static function customerSavingsPct(float $finalPrice, ?float $compareAtPrice): float
    {
        if ($compareAtPrice === null || $compareAtPrice <= 0 || $compareAtPrice <= $finalPrice) {
            return 0.0;
        }
        return round(($compareAtPrice - $finalPrice) / $compareAtPrice * 100, 2);
    }

    /**
     * Estado de la validación de margen para un precio propuesto.
     * Retorna: 'ok' | 'warn_below_target' | 'warn_below_min' | 'block_below_cost'.
     *
     * Reglas:
     *  - finalPrice < effectiveCost → block_below_cost (requiere liquidation_mode)
     *  - finalPrice < floorPrice    → warn_below_min (descuento rompe guardrail)
     *  - margenReal < targetMargin  → warn_below_target (informativo)
     *  - resto                      → ok
     */
    public static function classifyPrice(
        float $finalPrice,
        float $effectiveCost,
        ?float $targetMarginPct,
        ?float $minMarginPct
    ): string {
        if ($finalPrice < $effectiveCost) {
            return 'block_below_cost';
        }

        if ($minMarginPct !== null && $minMarginPct > 0) {
            $floor = self::floorPrice($effectiveCost, $minMarginPct);
            if ($finalPrice < $floor) {
                return 'warn_below_min';
            }
        }

        if ($targetMarginPct !== null && $targetMarginPct > 0) {
            $actualMargin = self::marginPct($finalPrice, $effectiveCost);
            if ($actualMargin < $targetMarginPct) {
                return 'warn_below_target';
            }
        }

        return 'ok';
    }

    /**
     * Snapshot completo de cálculo — utilizado por el endpoint
     * /api/items/{id}/calculate-price (Fase 2) y por el chip de margen del form.
     *
     * @return array{
     *   effective_cost: float,
     *   list_price: float|null,
     *   floor_price: float,
     *   final_price: float,
     *   margin_actual_pct: float,
     *   markup_actual_pct: float,
     *   profit_per_unit: float,
     *   customer_savings: float,
     *   customer_savings_pct: float,
     *   status: string,
     * }
     */
    public static function snapshot(
        float $costUnit,
        float $landedCostExtraPct = 0,
        ?float $targetMarginPct = null,
        ?float $minMarginPct = null,
        ?float $salePrice = null,
        float $discountPct = 0,
        ?float $compareAtPrice = null
    ): array {
        $effectiveCost = self::effectiveCost($costUnit, $landedCostExtraPct);

        $listPrice = null;
        if ($targetMarginPct !== null && $targetMarginPct > 0 && $targetMarginPct < 100) {
            $listPrice = self::listPriceFromMargin($effectiveCost, $targetMarginPct);
        }

        $actualSale = $salePrice ?? $listPrice ?? $effectiveCost;
        $finalPrice = self::finalPrice($actualSale, $discountPct);
        $floor      = self::floorPrice($effectiveCost, $minMarginPct ?? 0);

        return [
            'effective_cost'       => $effectiveCost,
            'list_price'           => $listPrice,
            'floor_price'          => $floor,
            'final_price'          => $finalPrice,
            'margin_actual_pct'    => self::marginPct($finalPrice, $effectiveCost),
            'markup_actual_pct'    => self::markupPct($finalPrice, $effectiveCost),
            'profit_per_unit'      => self::profitPerUnit($finalPrice, $effectiveCost),
            'customer_savings'     => self::customerSavings($finalPrice, $compareAtPrice),
            'customer_savings_pct' => self::customerSavingsPct($finalPrice, $compareAtPrice),
            'status'               => self::classifyPrice($finalPrice, $effectiveCost, $targetMarginPct, $minMarginPct),
        ];
    }
}
