<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class CourierCompany extends Model
{
    protected $connection = 'tenant';

    protected $fillable = [
        'name', 'is_active', 'sort_order',
        // L3 — Carrier API integration
        'api_driver', 'api_key', 'api_secret', 'api_endpoint', 'api_sandbox', 'api_meta',
    ];

    protected $casts = [
        'is_active'   => 'bool',
        'api_sandbox' => 'bool',
        'api_meta'    => 'array',
        // api_key y api_secret — encriptar DESPUÉS de migrar datos con: php artisan tenants:encrypt-credentials
    ];

    /** Ocultar credenciales al serializar a JSON (logs, API responses) */
    protected $hidden = ['api_key', 'api_secret'];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function hasApiIntegration(): bool
    {
        return !empty($this->api_key) && !in_array($this->api_driver ?? 'manual', ['manual', '']);
    }

    public function makeCarrierService(): \App\Services\Tenant\Carrier\CarrierServiceInterface
    {
        return \App\Services\Tenant\Carrier\CarrierServiceFactory::make($this);
    }
}
