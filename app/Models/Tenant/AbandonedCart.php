<?php

namespace App\Models\Tenant;

class AbandonedCart extends ModelTenant
{
    protected $table = 'abandoned_carts';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($cart) {
            if (empty($cart->expires_at)) {
                $cart->expires_at = now()->addHours(48);
            }
        });
    }

    protected $fillable = [
        'session_token',
        'user_id',
        'items',
        'subtotal',
        'item_count',
        'customer_email',
        'customer_phone',
        'customer_name',
        'recovered_at',
        'reminder_sent_at',
        'expires_at',
        'reminder_count',
        'last_reminder_at',
        'discount_code',
    ];

    protected $casts = [
        'items'            => 'array',
        'subtotal'         => 'float',
        'item_count'       => 'integer',
        'recovered_at'     => 'datetime',
        'expires_at'       => 'datetime',
        'reminder_sent_at' => 'datetime',
        'reminder_count'   => 'integer',
        'last_reminder_at' => 'datetime',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Solo carritos activos (no recuperados y no expirados). */
    public function scopeActive($q)
    {
        return $q->whereNull('recovered_at')
                 ->where(function ($q) {
                     $q->whereNull('expires_at')
                       ->orWhere('expires_at', '>', now());
                 });
    }

    /** Carritos expirados (para limpieza o análisis). */
    public function scopeExpired($q)
    {
        return $q->whereNull('recovered_at')
                 ->where('expires_at', '<=', now());
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Marca el carrito como recuperado (se convirtió en orden).
     */
    public function markAsRecovered(): void
    {
        $this->update(['recovered_at' => now()]);
    }
}
