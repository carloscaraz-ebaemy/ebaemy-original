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
        'thumb_image_url',
        'secondary_image_url',
        'gallery_image_urls',
        'seller_whatsapp',
        'category_name',
        'marketplace_category_id',
        'brand_name',
        'search_text',
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
                     ->where('stock', '>', 0)
                     ->hasImage();
    }

    /**
     * Solo listings con alguna imagen mostrable. Un producto sin foto rompe
     * la card del marketplace (queda el bloque "Sin imagen") y no vende, asi
     * que se oculta del listado publico hasta que el seller suba una foto.
     *
     * "Tiene imagen" = la del producto padre O la de al menos una variante
     * activa (los productos con variantes suelen no tener foto propia, la
     * card hereda primary_image_url de la variante principal — ver
     * MarketplaceController::decorateListingsWithVariantData).
     *
     * El seller ve los productos ocultos por esta regla en el panel:
     * ItemController::marketplaceStats() -> no_image / no_image_titles.
     */
    public function scopeHasImage($query)
    {
        return $query->where(function ($q) {
            $q->where(function ($w) {
                $w->whereNotNull('image_url')->where('image_url', '<>', '');
            })->orWhereExists(function ($sub) {
                $sub->select(\DB::raw(1))
                    ->from('marketplace_listing_variants as lvimg')
                    ->whereColumn('lvimg.listing_id', 'marketplace_listings.id')
                    ->where('lvimg.is_active', true)
                    ->whereNotNull('lvimg.image_url')
                    ->where('lvimg.image_url', '<>', '');
            });
        });
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

    /**
     * Tokeniza una query igual que scopeSearch: colapsa espacios y descarta
     * palabras de 1 caracter (ruido), salvo que sea la unica. Compartido por
     * scopeSearch y textRelevanceSql para que filtrar y ordenar usen SIEMPRE
     * los mismos tokens.
     *
     * @return array<int,string>
     */
    public static function searchTokens(?string $q): array
    {
        if (!$q) return [];
        $q = trim(preg_replace('/\s+/', ' ', $q));
        if ($q === '') return [];

        $tokens = array_values(array_filter(
            explode(' ', $q),
            fn ($t) => mb_strlen($t) >= 2
        ));

        return $tokens ?: [$q];
    }

    /**
     * Expresion SQL de RELEVANCIA TEXTUAL para ordenar resultados de busqueda.
     *
     * Sin esto, scopeSearch filtra con LIKE '%token%' — que tambien casa a
     * MITAD de palabra — y el orden se decide solo por destacados/vistas: al
     * buscar "polo" el primer resultado era "esPOLOn calcaneo" porque tenia
     * mas vistas que cualquier polo real. Aqui puntuamos cada token, y el
     * TITULO pesa mas que el resto del texto indexado: `search_text` mezcla
     * descripcion + marca + categoria, asi que casar por CATEGORIA no puede
     * valer lo mismo que casar por nombre (al buscar "zapatilla", una
     * plantilla de categoria "calzado" empataba con "malla para lavado de
     * ZAPATILLAS" y le ganaba por vistas):
     *
     *   6 → el TITULO empieza por el token            ("Polo manga corta")
     *   5 → una PALABRA del titulo empieza por el      ("Camiseta polo")
     *   4 → el texto indexado empieza por el token
     *   3 → una palabra del texto indexado empieza     (marca, categoria)
     *   2 → casa en el titulo a mitad de palabra       tolerancia de respaldo
     *   1 → solo casa a mitad de palabra en el resto   ("esPOLOn")
     *
     * El titulo se compara tal cual: la colacion utf8mb4_unicode_ci ya ignora
     * tildes y mayusculas. `search_text` lo normaliza el indexador.
     *
     * La puntuacion de los tokens se suma, asi una query de varias palabras
     * premia al que acierta en todas. Los sinonimos de SearchSynonyms puntuan
     * como el token original: si "polo" trajo "camiseta", esa camiseta merece
     * la misma frontera de palabra.
     *
     * Devuelve null si no hay query (el llamante omite el termino).
     *
     * Los literales van INLINE y no como bindings a proposito: esta expresion
     * se reutiliza dentro de un window function en el SELECT y otra vez en el
     * ORDER BY, y duplicar bindings en dos sitios rompe el COUNT de la
     * paginacion. Por eso cada token se sanea antes a solo letras, digitos y
     * espacio — nada que pueda cerrar la cadena ni inyectar SQL.
     */
    public static function textRelevanceSql(?string $q): ?string
    {
        $tokens = static::searchTokens($q);
        if (empty($tokens)) return null;

        $terms = [];
        foreach (array_slice($tokens, 0, 5) as $tok) {
            $variants = array_filter(array_map(
                [static::class, 'sanitizeSqlLiteral'],
                \App\Services\System\SearchSynonyms::expand($tok)
            ));
            $plain = static::sanitizeSqlLiteral($tok);
            if (empty($variants) && $plain === '') continue;

            $titleStart = [];
            $titleWord  = [];
            $textStart  = [];
            $textWord   = [];
            foreach ($variants as $v) {
                $titleStart[] = "title LIKE '{$v}%'";
                $titleWord[]  = "title LIKE '% {$v}%'";
                $textStart[]  = "search_text LIKE '{$v}%'";
                $textWord[]   = "search_text LIKE '% {$v}%'";
            }
            if (empty($textStart)) continue;

            $titleHit = $plain !== '' ? "title LIKE '%{$plain}%'" : '1 = 0';

            // Bonificacion de +1 cuando acierta la palabra EXACTA que se
            // tecleo, no un sinonimo: buscando "zapatilla", una zapatilla
            // debe ir antes que un zapato, aunque ambos casen en el titulo.
            if ($plain !== '') {
                $terms[] = "(CASE WHEN title LIKE '{$plain}%' OR title LIKE '% {$plain}%' THEN 1 ELSE 0 END)";
            }

            $terms[] = '(CASE'
                . ' WHEN ' . implode(' OR ', $titleStart) . ' THEN 6'
                . ' WHEN ' . implode(' OR ', $titleWord) . ' THEN 5'
                . ' WHEN ' . implode(' OR ', $textStart) . ' THEN 4'
                . ' WHEN ' . implode(' OR ', $textWord) . ' THEN 3'
                . " WHEN {$titleHit} THEN 2"
                . ' ELSE 1 END)';
        }

        return empty($terms) ? null : '(' . implode(' + ', $terms) . ')';
    }

    /**
     * Deja solo letras (con tildes), digitos, espacio y guion: lo justo para
     * un LIKE. Todo lo demas se cae, de modo que el resultado es seguro de
     * interpolar entre comillas simples en SQL.
     */
    protected static function sanitizeSqlLiteral(string $token): string
    {
        $clean = preg_replace('/[^\p{L}\p{N} \-]+/u', '', $token);

        return mb_substr(trim((string) $clean), 0, 60, 'UTF-8');
    }

    /**
     * Búsqueda tolerante: divide la query en palabras y exige que CADA
     * palabra aparezca en title/category/brand. Asi 'x 24' encuentra
     * 'x24 Hojas', 'planta artificial' encuentra 'planta de bambu artificial',
     * etc. Sin la division, una sola palabra inexacta hace 0 resultados.
     *
     * Tambien colapsa espacios multiples y trim — defensivo.
     */
    public function scopeSearch($query, ?string $q)
    {
        // Tokenizar: cada palabra >=2 chars se exige presente. Tokens cortos
        // (1 char) suelen ser ruido — los ignoramos a menos que sea el unico.
        $tokens = static::searchTokens($q);
        if (empty($tokens)) return $query;

        return $query->where(function ($w) use ($tokens) {
            foreach ($tokens as $tok) {
                $like = '%' . $tok . '%';
                // Token normalizado + sinónimos (asiento→silla, etc.), todos
                // insensibles a tildes, contra el índice search_text.
                $variants = \App\Services\System\SearchSynonyms::expand($tok);
                $w->where(function ($sub) use ($like, $variants) {
                    foreach ($variants as $v) {
                        $sub->orWhere('search_text', 'like', '%' . $v . '%');
                    }
                    // Fallback: columnas originales + SKU/código interno.
                    $sub->orWhere('title', 'like', $like)
                        ->orWhere('category_name', 'like', $like)
                        ->orWhere('brand_name', 'like', $like)
                        ->orWhere('internal_id', 'like', $like);
                });
            }
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
     * Quita el sufijo de variante "_mp" de una URL de imagen para apuntar a la
     * variante 'main' (1200px, aspect ratio ORIGINAL sin recorte). El _mp es un
     * recorte cuadrado 1080x1080 (fit()) ideal para la grilla de cards, pero en
     * la PÁGINA DE DETALLE queremos mostrar el producto completo.
     *   "foo-abc_mp.webp" → "foo-abc.webp"
     */
    private static function toFullImageUrl(?string $url): ?string
    {
        if (empty($url)) return null;
        // Reemplaza "_mp" inmediatamente antes de la extensión (último punto).
        return preg_replace('/_mp(\.[A-Za-z0-9]+)$/', '$1', $url);
    }

    /**
     * Imagen principal SIN recorte para la página de detalle. Las cards siguen
     * usando image_url (cuadrado _mp) — esto NO cambia la lógica de la grilla.
     */
    public function getImageFullUrlAttribute(): ?string
    {
        return self::toFullImageUrl($this->image_url);
    }

    /**
     * Galería en resolución completa (aspect ratio original) para el detalle.
     */
    public function getGalleryFullImageUrlsAttribute(): array
    {
        $urls = is_array($this->gallery_image_urls) ? $this->gallery_image_urls : [];
        $full = array_map([self::class, 'toFullImageUrl'], $urls);
        return array_values(array_filter($full));
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
