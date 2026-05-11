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
        'secondary_image_url',
        'gallery_image_urls',
        'seller_whatsapp',
        'category_name',
        'marketplace_category_id',
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
        'is_featured',
        'featured_until',
        'featured_score',
        // Fase 0 — sync de descuentos del tenant al marketplace.
        // is_on_offer/original_price/offer_ends_at/discount_pct se calculan
        // en MarketplaceListingSyncService::buildPayload aplicando el
        // PromotionEngine del tenant con canal 'marketplace'.
        'is_on_offer',
        'original_price',
        'offer_ends_at',
        'discount_pct',
        'discount_source',
        // Fase 0 — variantes (preparación). has_variants + min/max price
        // permite a la UI mostrar "Desde S/X" sin tener que joinar con
        // item_variants en cada render. La estructura completa de variantes
        // (selector + dispatch) llega en Fase 0.B con marketplace_listing_variants.
        'has_variants',
        'min_price',
        'max_price',
        // Packs / conjuntos (bundles del tenant publicados como combos).
        // is_pack=true cuando item.is_set=true en el tenant. pack_contents
        // tiene el detalle desnormalizado para no tener que ir al tenant DB
        // en cada render. pack_stock es el max armable = min(comp.stock/qty).
        'is_pack',
        'pack_contents',
        'pack_stock',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'tenant_verified'   => 'boolean',
        'is_featured'       => 'boolean',
        'is_on_offer'       => 'boolean',
        'has_variants'      => 'boolean',
        'is_pack'           => 'boolean',
        'pack_contents'     => 'array',
        'pack_stock'        => 'integer',
        'gallery_image_urls'=> 'array',
        'price'           => 'float',
        'mp_price'        => 'float',
        'original_price'  => 'float',
        'min_price'       => 'float',
        'max_price'       => 'float',
        'stock'           => 'integer',
        'view_count'      => 'integer',
        'lead_count'      => 'integer',
        'click_count'     => 'integer',
        'sort_score'      => 'integer',
        'featured_score'  => 'integer',
        'discount_pct'    => 'integer',
        'avg_rating'      => 'float',
        'rating_count'    => 'integer',
        'synced_at'       => 'datetime',
        'featured_until'  => 'datetime',
        'offer_ends_at'   => 'datetime',
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

    /**
     * Variantes espejo del item_variants del tenant. Solo aplica cuando
     * has_variants=true. Llenado por MarketplaceListingSyncService::syncVariants.
     */
    public function variants()
    {
        return $this->hasMany(MarketplaceListingVariant::class, 'listing_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopePublished($query)
    {
        return $query->where('is_active', true)
                     ->where('status', 'active')
                     ->where('stock', '>', 0);
    }

    /**
     * Listings destacados activos: marcados como featured y con expiración
     * en el futuro (o sin expiración). Sin restringir publicado — el caller
     * suele encadenar published()->featured() para el listing comercial.
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true)
                     ->where(function ($q) {
                         $q->whereNull('featured_until')
                           ->orWhere('featured_until', '>', now());
                     });
    }

    /**
     * Listings con oferta vigente: flag is_on_offer=true Y la promo no
     * expiró (offer_ends_at NULL o futuro). Encadenar con published().
     */
    public function scopeOnOffer($query)
    {
        return $query->where('is_on_offer', true)
                     ->where(function ($q) {
                         $q->whereNull('offer_ends_at')
                           ->orWhere('offer_ends_at', '>', now());
                     });
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

    /**
     * Filtra listings por categoría oficial del marketplace (FK) incluyendo
     * toda la descendencia del nodo. Usa `depth_path` denormalizado para
     * resolver los IDs de descendientes con una sola query.
     *
     * Pasar null o 0 es no-op (no aplica filtro).
     */
    public function scopeInOfficialCategory($query, ?int $categoryId)
    {
        if (!$categoryId) return $query;

        $node = MarketplaceCategory::query()->find($categoryId);
        if (!$node) {
            return $query->where('marketplace_category_id', $categoryId);
        }

        $ids = $node->descendantAndSelfIds();
        return $query->whereIn('marketplace_category_id', $ids);
    }

    public function marketplaceCategory()
    {
        return $this->belongsTo(MarketplaceCategory::class, 'marketplace_category_id');
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    /**
     * Precio visible en el marketplace: mp_price si es > 0, si no price base.
     *
     * Tratamos mp_price=0 como "no hay override" (equivalente a null) — el
     * form del tenant muestra el input como "vacío = usar precio normal",
     * pero Element UI a veces deja 0 en vez de null al limpiar. Sin esta
     * guarda, ese 0 ganaba sobre el precio real y productos válidos
     * aparecían como S/0 en el marketplace.
     */
    public function getDisplayPriceAttribute(): float
    {
        $mp = $this->mp_price !== null ? (float) $this->mp_price : null;
        return $mp !== null && $mp > 0 ? $mp : (float) $this->price;
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

    /**
     * Subdominio del tenant (primera parte del FQDN). Usado para construir
     * la URL de la página pública por tienda en el marketplace.
     */
    public function getSubdomainAttribute(): ?string
    {
        if (empty($this->tenant_fqdn)) {
            return null;
        }
        return strtolower(strtok($this->tenant_fqdn, '.')) ?: null;
    }

    /**
     * URL pública de la tienda dentro del marketplace central:
     *   ebaemy.com/marketplace/tienda/{subdomain}
     */
    public function getStoreUrlAttribute(): ?string
    {
        $sub = $this->subdomain;
        return $sub ? url('/marketplace/tienda/' . $sub) : null;
    }
}
