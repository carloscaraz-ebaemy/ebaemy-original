<?php

namespace App\Models\System;

use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Tienda de otro sistema (PRO 9) que publica en el marketplace por API.
 * Ver la migración create_marketplace_external_sellers_table.
 */
class MarketplaceExternalSeller extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_external_sellers';

    protected $fillable = [
        'hostname_id', 'source', 'fqdn', 'name', 'api_key', 'api_secret',
        'webhook_url', 'is_active', 'last_request_at',
    ];

    protected $hidden = ['api_secret'];

    protected $casts = [
        'api_secret'      => 'encrypted',
        'is_active'       => 'boolean',
        'last_request_at' => 'datetime',
    ];

    public function hostname()
    {
        return $this->belongsTo(Hostname::class);
    }

    public static function forHostname(?int $hostnameId): ?self
    {
        return $hostnameId ? static::where('hostname_id', $hostnameId)->first() : null;
    }

    /** Firma HMAC-SHA256 de "{timestamp}\n{cuerpo}" con el secreto del vendedor. */
    public function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . "\n" . $body, (string) $this->api_secret);
    }
}
