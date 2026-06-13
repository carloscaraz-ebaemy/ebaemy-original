<?php

namespace App\Models\Tenant;

/**
 * Snapshot de un item con margen erosionado, detectado por el job nocturno
 * `pricing:monitor-floor` (Fase 4 rediseño precios).
 *
 * La tabla se reemplaza completa en cada corrida — refleja el estado actual,
 * no es histórico. El reporte admin tenant lee de aquí.
 *
 * Ver [[project_pricing_redesign]].
 */
class PricingMarginAlert extends ModelTenant
{
    protected $table = 'pricing_margin_alerts';

    protected $fillable = [
        'item_id',
        'item_description',
        'category_id',
        'category_name',
        'severity',
        'effective_cost',
        'sale_price',
        'floor_price',
        'compare_at_price',
        'margin_pct',
        'applied_min_margin_pct',
        'loss_per_unit',
        'liquidation_mode',
        'apply_store',
        'marketplace_publishable',
        'detected_at',
    ];

    protected $casts = [
        'effective_cost'          => 'float',
        'sale_price'              => 'float',
        'floor_price'             => 'float',
        'compare_at_price'        => 'float',
        'margin_pct'              => 'float',
        'applied_min_margin_pct'  => 'float',
        'loss_per_unit'           => 'float',
        'liquidation_mode'        => 'boolean',
        'apply_store'             => 'boolean',
        'marketplace_publishable' => 'boolean',
        'detected_at'             => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function scopeLoss($query)
    {
        return $query->where('severity', 'loss');
    }

    public function scopeBelowFloor($query)
    {
        return $query->where('severity', 'below_floor');
    }
}
