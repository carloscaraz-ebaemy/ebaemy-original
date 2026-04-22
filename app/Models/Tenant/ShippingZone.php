<?php

namespace App\Models\Tenant;

use App\Models\Tenant\ModelTenant;

/**
 * Zona de envío configurable por tenant. Asocia una lista de district_ids a
 * un costo y tiempo estimado. Si el distrito del cliente no matchea ninguna
 * zona, se usa la marcada con `is_default=true` (fallback).
 */
class ShippingZone extends ModelTenant
{
    protected $table = 'shipping_zones';

    protected $fillable = [
        'name',
        'cost',
        'estimated_days',
        'district_ids',
        'is_default',
        'is_pickup',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'cost'           => 'float',
        'estimated_days' => 'integer',
        'district_ids'   => 'array',
        'is_default'     => 'boolean',
        'is_pickup'      => 'boolean',
        'is_active'      => 'boolean',
        'sort_order'     => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Busca la zona aplicable para un distrito. Si no hay match explícito,
     * devuelve la default (fallback de provincias/otros).
     */
    public static function resolveForDistrict(?string $districtId, bool $isPickup = false): ?self
    {
        if ($isPickup) {
            return self::active()->where('is_pickup', true)->first();
        }

        if ($districtId) {
            $zones = self::active()->whereNotNull('district_ids')->get();
            foreach ($zones as $zone) {
                $ids = $zone->district_ids ?? [];
                if (in_array($districtId, $ids, true)) {
                    return $zone;
                }
            }
        }

        return self::active()->where('is_default', true)->first();
    }
}
