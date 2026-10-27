<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Solicitud de publicidad de una tienda. Ver la migración
 * 2026_10_27_000001_create_marketplace_ad_requests_table.
 */
class MarketplaceAdRequest extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_ad_requests';

    protected $fillable = [
        'hostname_id', 'subdomain', 'store_name', 'requested_by', 'contact_phone',
        'scope', 'scope_detail', 'zone', 'budget', 'days', 'notes',
        'status', 'utm_campaign', 'spent', 'admin_notes', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'budget'    => 'float',
        'spent'     => 'float',
        'days'      => 'integer',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public const STATUSES = [
        'solicitada' => 'Solicitada',
        'pagada'     => 'Pagada',
        'activa'     => 'Activa',
        'terminada'  => 'Terminada',
        'rechazada'  => 'Rechazada',
    ];

    public const SCOPES = [
        'tienda'    => 'Lo más atractivo de la tienda',
        'categoria' => 'Una categoría',
        'productos' => 'Productos concretos',
    ];

    public const ZONES = ['lima' => 'Lima', 'peru' => 'Todo el Perú'];

    /** Solicitudes abiertas: una tienda no puede tener dos a la vez. */
    public const OPEN = ['solicitada', 'pagada', 'activa'];

    protected static function booted(): void
    {
        // Nombre de campaña estable desde el alta: es lo que se pega en Meta.
        static::created(function (self $r) {
            if (!$r->utm_campaign) {
                $r->utm_campaign = 'tienda-' . $r->subdomain . '-' . $r->created_at->format('ym') . '-' . $r->id;
                $r->saveQuietly();
            }
        });
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Enlace corto de la tienda (ver MarketplaceController::shortLink). */
    public function shortUrl(): string
    {
        return url('/m/t/' . $this->subdomain);
    }

    /** Lo que se pega en «Parámetros de URL» del anuncio en Meta. */
    public function urlParams(): string
    {
        return 'utm_source=facebook&utm_medium=paid&utm_campaign=' . $this->utm_campaign;
    }

    /**
     * Pedidos y consultas que llegaron con el utm_campaign de esta solicitud.
     * Los pedidos se cuentan en el pedido de ESTA tienda (no en el padre, que
     * puede llevar productos de otras), igual que los ve el dueño.
     */
    public function results(): array
    {
        if (!$this->utm_campaign) {
            return ['orders' => 0, 'revenue' => 0.0, 'leads' => 0];
        }
        $conn = DB::connection('system');
        $orders = $conn->table('marketplace_orders as p')
            ->join('tenant_marketplace_orders as t', 't.marketplace_order_id', '=', 'p.id')
            ->where('p.utm_campaign', $this->utm_campaign)
            ->where('t.hostname_id', $this->hostname_id)
            ->where('t.status', '<>', 'cancelled')
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(t.subtotal), 0) as revenue')
            ->first();
        $leads = $conn->table('marketplace_leads')
            ->where('utm_campaign', $this->utm_campaign)
            ->where('hostname_id', $this->hostname_id)
            ->count();

        return [
            'orders'  => (int) ($orders->n ?? 0),
            'revenue' => round((float) ($orders->revenue ?? 0), 2),
            'leads'   => (int) $leads,
        ];
    }
}
