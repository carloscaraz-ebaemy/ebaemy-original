<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Un extra contratado por una tienda. Vale mientras esté `active` y pagado
 * hasta hoy (`paid_until`). Vencer no cambia el estado: el extra deja de
 * valer solo, y vuelve en cuanto se registra el pago.
 */
class ClientAddon extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'client_id', 'addon_key', 'status', 'monthly_price', 'quota',
        'starts_at', 'paid_until', 'notes', 'enabled_by',
    ];

    protected $casts = [
        'monthly_price' => 'float',
        'quota'         => 'integer',
        'starts_at'     => 'date',
        'paid_until'    => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /** Activo y pagado hasta hoy incluido. */
    public function scopeVigente($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('paid_until')
            ->whereDate('paid_until', '>=', now()->toDateString());
    }

    public function isVigente(): bool
    {
        return $this->status === 'active'
            && $this->paid_until !== null
            && $this->paid_until->endOfDay()->isFuture();
    }

    /**
     * Registra un mes pagado: extiende desde el último día pagado o, si ya
     * venció, desde hoy. Así pagar tarde no regala los días vencidos.
     */
    public function registrarMesPagado(): void
    {
        $desde = ($this->paid_until && $this->paid_until->isFuture()) ? $this->paid_until : now()->startOfDay();
        $this->paid_until = $desde->copy()->addMonthNoOverflow();
        $this->save();
    }
}
