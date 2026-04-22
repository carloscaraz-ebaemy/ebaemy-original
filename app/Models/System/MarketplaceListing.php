<?php

namespace App\Models\System;

use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo de un item publicado por un tenant en el marketplace de ebaemy.com.
 * Vive en la BD central y se alimenta desde el comando marketplace:sync.
 */
class MarketplaceListing extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_listings';

    protected $fillable = [
        'hostname_id',
        'tenant_fqdn',
        'tenant_name',
        'tenant_logo_url',
        'tenant_verified',
        'client_id',
        'remote_item_id',
        'title',
        'slug',
        'internal_id',
        'short_description',
        'description',
        'image_url',
        'category_name',
        'brand_name',
        'price',
        'mp_price',
        'stock',
        'status',
        'is_active',
        'rejection_reason',
        'sort_score',
        'view_count',
        'lead_count',
        'click_count',
        'synced_at',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'tenant_verified' => 'boolean',
        'price'           => 'float',
        'mp_price'        => 'float',
        'stock'           => 'integer',
        'view_count'      => 'integer',
        'lead_count'      => 'integer',
        'click_count'     => 'integer',
        'sort_score'      => 'integer',
        'avg_rating'      => 'float',
        'rating_count'    => 'integer',
        'synced_at'       => 'datetime',
    ];

    public function hostname()
    {
        return $this->belongsTo(Hostname::class, 'hostname_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function leads()
    {
        return $this->hasMany(MarketplaceLead::class, 'listing_id');
    }

    public function reviews()
    {
        return $this->hasMany(MarketplaceReview::class, 'listing_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('is_active', true)
                     ->where('status', 'active')
                     ->where('stock', '>', 0);
    }

    public function scopeSearch($query, ?string $q)
    {
        if (!$q) return $query;
        return $query->where(function ($w) use ($q) {
            $w->where('title', 'like', "%{$q}%")
              ->orWhere('category_name', 'like', "%{$q}%")
              ->orWhere('brand_name', 'like', "%{$q}%");
        });
    }

    public function scopeCategory($query, ?string $category)
    {
        if (!$category) return $query;
        return $query->where('category_name', $category);
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Precio visible en el marketplace: mp_price si existe, si no price base.
     */
    public function getDisplayPriceAttribute(): float
    {
        return (float) ($this->mp_price ?? $this->price);
    }

    /**
     * URL absoluta de la ficha pública del listing.
     */
    public function getPublicUrlAttribute(): string
    {
        return url('/marketplace/item/' . $this->slug);
    }

    /**
     * URL al storefront del tenant original (para redirect "Comprar").
     * Si el tenant expone el item en su ecommerce, apunta al detalle.
     */
    public function getTenantItemUrlAttribute(): string
    {
        $scheme = request()->secure() ? 'https' : 'http';
        $base   = rtrim("{$scheme}://{$this->tenant_fqdn}", '/');
        return $base . '/ecommerce/item/' . $this->remote_item_id;
    }

    /**
     * URL al storefront del tenant con UTM tracking para que el tenant sepa
     * de dónde viene el visitante. Se usa desde el endpoint /marketplace/go.
     */
    public function getTenantItemUrlWithUtmAttribute(): string
    {
        $url = $this->tenant_item_url;
        $utm = http_build_query([
            'utm_source'   => 'ebaemy_marketplace',
            'utm_medium'   => 'referral',
            'utm_campaign' => 'listing_' . $this->id,
            'ref'          => 'ebaemy',
        ]);
        return $url . (str_contains($url, '?') ? '&' : '?') . $utm;
    }

    /**
     * Ratio de conversión click → lead (%). Útil en el panel admin.
     */
    public function getConversionRateAttribute(): float
    {
        if ($this->click_count <= 0) return 0;
        return round(($this->lead_count / $this->click_count) * 100, 1);
    }

    /**
     * Nombre visible de la tienda vendedora. Prioridad: tenant_name (trade_name)
     * y cae al fqdn si no se capturó todavía.
     */
    public function getSellerDisplayAttribute(): string
    {
        return $this->tenant_name ?: $this->tenant_fqdn;
    }
}
