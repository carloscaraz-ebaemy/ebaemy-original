<?php

namespace App\Models\Tenant;

use Carbon\Carbon;

class FlashSale extends ModelTenant
{
    protected $table = 'flash_sales';

    protected $fillable = ['title', 'subtitle', 'starts_at', 'ends_at', 'active'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'active'    => 'boolean',
    ];

    /**
     * Items que forman parte de esta flash sale (con precio especial en el pivot).
     */
    public function items()
    {
        return $this->belongsToMany(Item::class, 'flash_sale_items', 'flash_sale_id', 'item_id')
                    ->withPivot('flash_price')
                    ->withTimestamps();
    }

    /**
     * Scope: flash sales activas en este momento.
     */
    public function scopeActive($query)
    {
        $now = Carbon::now();
        return $query->where('active', true)
                     ->where('ends_at', '>', $now)
                     ->where(function ($q) use ($now) {
                         $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                     });
    }

    /**
     * Segundos restantes hasta que termine la oferta.
     */
    public function getSecondsRemainingAttribute(): int
    {
        return max(0, Carbon::now()->diffInSeconds($this->ends_at, false));
    }

    /**
     * Indica si la flash sale está en curso.
     */
    public function getIsActiveNowAttribute(): bool
    {
        $now = Carbon::now();
        return $this->active
            && $this->ends_at->gt($now)
            && ($this->starts_at === null || $this->starts_at->lte($now));
    }
}
