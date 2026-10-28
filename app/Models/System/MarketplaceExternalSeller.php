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

    public const STATUSES = [
        'pending'   => 'Por aprobar',
        'approved'  => 'Aprobada',
        'rejected'  => 'Rechazada',
        'suspended' => 'Suspendida',
    ];

    protected $table = 'marketplace_external_sellers';

    protected $fillable = [
        'hostname_id', 'source', 'fqdn', 'name', 'contact_phone', 'contact_email', 'api_key', 'api_secret',
        'webhook_url', 'status', 'admin_notes', 'reviewed_at', 'last_request_at',
    ];

    protected $hidden = ['api_secret'];

    protected $casts = [
        'api_secret'      => 'encrypted',
        'reviewed_at'     => 'datetime',
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

    /** Solo una tienda aprobada vende: sus productos se ven y recibe pedidos. */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'approved';
    }

    /** Puede usar la API (enviar productos, consultar su estado) aunque no esté aprobada aún. */
    public function canUseApi(): bool
    {
        return in_array($this->status, ['pending', 'approved'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Cambia el estado y muestra u oculta de una vez todos sus productos.
     * Al aprobar salen los que el vendedor dejó activos ('active'); los que él
     * pausó siguen pausados.
     */
    public function changeStatus(string $status, ?string $notes = null): void
    {
        $this->update([
            'status'      => $status,
            'admin_notes' => $notes ?? $this->admin_notes,
            'reviewed_at' => now(),
        ]);

        $query = MarketplaceListing::where('hostname_id', $this->hostname_id);
        $status === 'approved'
            ? $query->where('status', 'active')->update(['is_active' => true])
            : $query->update(['is_active' => false]);
    }

    /** Firma HMAC-SHA256 de "{timestamp}\n{cuerpo}" con el secreto del vendedor. */
    public function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . "\n" . $body, (string) $this->api_secret);
    }
}
