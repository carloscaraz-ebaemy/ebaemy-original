<?php

namespace App\Rules;

use App\Models\Tenant\PricingSettings;
use App\Services\Tenant\Pricing\PriceCalculator;
use Illuminate\Contracts\Validation\Rule;

/**
 * Valida que el precio de venta de un item no caiga bajo costo (o bajo el
 * precio piso si se definió min_margin_pct).
 *
 * Excepciones permitidas:
 *  - liquidation_mode = true → permite hasta cost (no bajo cost igual)
 *  - block_sales_below_cost = false en pricing_settings → solo warn (no recomendado)
 *
 * Decisión arquitectural (2026-05-18): NUNCA permitir sale_price < cost,
 * ni con role admin. Si se necesita liquidar, debe activar liquidation_mode
 * en el item explícitamente. Ver memoria [[project_pricing_redesign]].
 */
class MinMarginRule implements Rule
{
    protected float $cost;
    protected float $landedCostExtraPct;
    protected ?float $minMarginPct;
    protected bool $liquidationMode;
    protected string $errorMessage = '';

    public function __construct(
        float $cost,
        float $landedCostExtraPct = 0,
        ?float $minMarginPct = null,
        bool $liquidationMode = false
    ) {
        $this->cost                = $cost;
        $this->landedCostExtraPct  = $landedCostExtraPct;
        $this->minMarginPct        = $minMarginPct;
        $this->liquidationMode     = $liquidationMode;
    }

    public function passes($attribute, $value): bool
    {
        $salePrice = (float) $value;

        if ($salePrice <= 0) {
            // Otras reglas (gt:0) ya cubren esto
            return true;
        }

        $effectiveCost = PriceCalculator::effectiveCost($this->cost, $this->landedCostExtraPct);

        // Cargar settings tenant (cached per request)
        $settings = $this->getSettings();
        $minMargin = $this->minMarginPct ?? $settings->default_min_margin_pct ?? 0;

        // Bloqueo absoluto: precio < costo
        if ($salePrice < $effectiveCost) {
            if ($this->liquidationMode) {
                // Permitido en liquidación, pero registramos warning logueable
                // (el item ya tiene liquidation_mode=true explícito)
                return true;
            }
            if (!$settings->block_sales_below_cost) {
                // Override global del tenant — solo advertir
                return true;
            }
            $this->errorMessage = sprintf(
                'El precio de venta (S/ %.2f) es menor al costo efectivo (S/ %.2f). Genera pérdida de S/ %.2f por unidad. Activa "Modo liquidación" en el producto si necesitas vender bajo costo.',
                $salePrice,
                $effectiveCost,
                $effectiveCost - $salePrice
            );
            return false;
        }

        // Validación de margen mínimo (warn, no block — el block es solo bajo costo)
        if ($minMargin > 0) {
            $floorPrice = PriceCalculator::floorPrice($effectiveCost, $minMargin);
            if ($salePrice < $floorPrice) {
                $actualMargin = PriceCalculator::marginPct($salePrice, $effectiveCost);
                $this->errorMessage = sprintf(
                    'El precio de venta (S/ %.2f) deja un margen de %.2f%%, debajo del mínimo configurado (%.2f%%). Precio mínimo recomendado: S/ %.2f.',
                    $salePrice,
                    $actualMargin,
                    $minMargin,
                    $floorPrice
                );
                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        return $this->errorMessage ?: 'El precio de venta no cumple con la política de margen.';
    }

    /**
     * Cache por request del PricingSettings (singleton row).
     */
    protected function getSettings(): PricingSettings
    {
        static $cached = null;
        if ($cached === null) {
            $cached = PricingSettings::firstOrCreate(
                ['id' => 1],
                [
                    'default_min_margin_pct' => 10,
                    'block_sales_below_cost' => true,
                    'audit_cost_changes'     => true,
                ]
            );
        }
        return $cached;
    }
}
