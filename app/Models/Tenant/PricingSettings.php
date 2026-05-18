<?php

namespace App\Models\Tenant;

/**
 * Configuración tenant del sistema de precios (singleton row id=1).
 *
 * Se crea con migración 2026_05_18_000003_create_pricing_settings_table
 * insertando una fila default. No exponer create/delete en UI — solo update.
 */
class PricingSettings extends ModelTenant
{
    protected $table = 'pricing_settings';

    protected $fillable = [
        'default_min_margin_pct',
        'block_sales_below_cost',
        'audit_cost_changes',
        'category_min_margins',
    ];

    protected $casts = [
        'default_min_margin_pct' => 'float',
        'block_sales_below_cost' => 'boolean',
        'audit_cost_changes'     => 'boolean',
        'category_min_margins'   => 'array',
    ];

    /**
     * Helper para obtener el margen mínimo aplicable a una categoría dada
     * (override de la default si está configurado).
     */
    public function minMarginFor(?int $categoryId): float
    {
        if ($categoryId !== null && is_array($this->category_min_margins)) {
            $override = $this->category_min_margins[(string) $categoryId]
                ?? $this->category_min_margins[$categoryId]
                ?? null;
            if ($override !== null) {
                return (float) $override;
            }
        }
        return (float) ($this->default_min_margin_pct ?? 0);
    }
}
