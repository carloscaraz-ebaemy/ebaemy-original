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
        // Resumen de la política de envío de la tienda (ver StoreShipping::summary()).
        'shipping',
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
        'shipping'          => 'array',
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
     * Listings que conservan su ficha publica: los de `published()` y ademas
     * los agotados. Un agotado temporal sigue siendo el mismo producto; si su
     * ficha daba 404, Google la sacaba del indice y al reponerlo volvia a
     * empezar de cero. La ficha lo muestra «Sin stock disponible», sin boton
     * de compra y con `OutOfStock` en los datos estructurados, y el carrito
     * sigue validando con `published()`, asi que no se puede comprar.
     *
     * Pausado, rechazado o desactivado por el vendedor: fuera (404).
     */
    public function scopeHasPublicPage($query)
    {
        return $query->where('is_active', true)
                     ->where('status', 'active')
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
     * Palabras de relleno que NO se exigen en el producto. scopeSearch pide
     * que aparezcan todas las palabras de la busqueda, asi que «zapatillas
     * para nina» dejaba fuera «Zapatillas nina rosadas» solo porque el titulo
     * no dice «para». Se comparan ya normalizadas (sin tildes, minusculas).
     */
    private const STOPWORDS = [
        'a', 'al', 'con', 'de', 'del', 'e', 'el', 'en', 'la', 'las', 'lo', 'los',
        'mas', 'mi', 'mis', 'muy', 'o', 'para', 'por', 'que', 'sin', 'su', 'sus',
        'tu', 'tus', 'u', 'un', 'una', 'unas', 'unos', 'y',
    ];

    /**
     * Tokeniza una query igual que scopeSearch: colapsa espacios, descarta
     * palabras de 1 caracter y las de relleno (STOPWORDS), salvo que no quede
     * ninguna. Compartido por scopeSearch y textRelevanceSql para que filtrar
     * y ordenar usen SIEMPRE los mismos tokens.
     *
     * @return array<int,string>
     */
    public static function searchTokens(?string $q): array
    {
        if (!$q) return [];
        $q = trim(preg_replace('/\s+/', ' ', $q));
        if ($q === '') return [];

        // Comas y barras separan palabras igual que el espacio: «polo,rojo».
        $palabras = preg_split('/[\s,;\/]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);

        $tokens = array_values(array_filter(
            $palabras,
            fn ($t) => mb_strlen($t) >= 2
        ));

        $utiles = array_values(array_filter(
            $tokens,
            fn ($t) => !in_array(
                \App\Services\System\MarketplaceListingSyncService::normalizeForSearch($t),
                self::STOPWORDS,
                true
            )
        ));

        return $utiles ?: ($tokens ?: [$q]);
    }

    /**
     * Patron REGEXP que casa `$palabra` solo al INICIO de una palabra del
     * texto indexado. Con `LIKE '%x%'` las variantes casaban a mitad de
     * palabra: «olla» -> «oya» traia «jOYA», «silla» -> «cilla» traia
     * «senCILLA» y «cama» traia «CAMAra». `$palabra` ya viene saneada por
     * sanitizeSqlLiteral (letras, digitos, espacio, guion): nada que el
     * motor de regex interprete.
     */
    protected static function wordStartRegex(string $palabra): string
    {
        return '(^|[^[:alnum:]])' . $palabra;
    }

    /**
     * Si el termino tecleado puede casar tambien a mitad de palabra. Solo
     * cuando lleva digitos («24» en «x24») o es largo (6+), donde casar
     * dentro de otra palabra ya no es casualidad («zapato» en
     * «portazapatos»). Las variantes generadas nunca: ver wordStartRegex.
     */
    protected static function allowsInnerMatch(string $normalizado): bool
    {
        return preg_match('/\d/', $normalizado) === 1 || mb_strlen($normalizado) >= 6;
    }

    /**
     * Variantes saneadas y sin repetir, listas para interpolar.
     *
     * @param  array<int,string> $palabras
     * @return array<int,string>
     */
    protected static function cleanVariants(array $palabras): array
    {
        return array_values(array_unique(array_filter(
            array_map([static::class, 'sanitizeSqlLiteral'], $palabras)
        )));
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
     *   6 → el TITULO empieza por la palabra           ("Polo manga corta")
     *   5 → una PALABRA del titulo empieza por ella     ("Camiseta polo")
     *   4 → una palabra del texto indexado empieza      (marca, categoria, variantes)
     *   3 → un SINONIMO en el titulo                    ("polo" -> "Camiseta")
     *   2 → un sinonimo en el resto del texto
     *   1 → solo casa a mitad de palabra                ("portaZAPATOS")
     *
     * «La palabra» incluye sus grafias (plural, erratas que suenan igual):
     * es lo mismo que se tecleo. Los sinonimos de significado van POR DEBAJO
     * de cualquier acierto de la palabra: antes puntuaban igual y lo que
     * traian («cafe» al buscar cafetera) se mezclaba con lo buscado.
     *
     * El titulo se compara tal cual: la colacion utf8mb4_unicode_ci ya ignora
     * tildes y mayusculas. `search_text` lo normaliza el indexador.
     *
     * La puntuacion de los tokens se suma, asi una query de varias palabras
     * premia al que acierta en todas.
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
            $grafias = static::cleanVariants(\App\Services\System\SearchSynonyms::spellings($tok));
            $sinonimos = array_values(array_diff(
                static::cleanVariants(\App\Services\System\SearchSynonyms::expand($tok)),
                $grafias
            ));
            $plain = static::sanitizeSqlLiteral($tok);
            if (empty($grafias)) continue;

            $titleStart = [];
            $titleWord  = [];
            $textWord   = [];
            foreach ($grafias as $v) {
                $titleStart[] = "title LIKE '{$v}%'";
                $titleWord[]  = "title LIKE '% {$v}%'";
                $textWord[]   = "search_text REGEXP '" . static::wordStartRegex($v) . "'";
            }

            $synTitle = [];
            $synText  = [];
            foreach ($sinonimos as $v) {
                $synTitle[] = "title LIKE '{$v}%' OR title LIKE '% {$v}%'";
                $synText[]  = "search_text REGEXP '" . static::wordStartRegex($v) . "'";
            }

            // Bonificacion de +1 cuando acierta la palabra EXACTA que se
            // tecleo, no una grafia alternativa: buscando "zapatilla", una
            // zapatilla debe ir antes que una "sapatilla" mal escrita.
            if ($plain !== '') {
                $terms[] = "(CASE WHEN title LIKE '{$plain}%' OR title LIKE '% {$plain}%' THEN 1 ELSE 0 END)";
            }

            // +2 cuando la palabra sale ENTERA (o en plural), no como inicio
            // de otra: buscando «cama», «Cama de madera» antes que «Cámara».
            $enteras = array_map(
                fn ($v) => "search_text REGEXP '" . static::wordStartRegex($v) . "(s|es)?([^[:alnum:]]|$)'",
                $grafias
            );
            $terms[] = '(CASE WHEN ' . implode(' OR ', $enteras) . ' THEN 2 ELSE 0 END)';

            $terms[] = '(CASE'
                . ' WHEN ' . implode(' OR ', $titleStart) . ' THEN 6'
                . ' WHEN ' . implode(' OR ', $titleWord) . ' THEN 5'
                . ' WHEN ' . implode(' OR ', $textWord) . ' THEN 4'
                . ($synTitle ? ' WHEN ' . implode(' OR ', $synTitle) . ' THEN 3' : '')
                . ($synText ? ' WHEN ' . implode(' OR ', $synText) . ' THEN 2' : '')
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
                // Token normalizado + grafias + sinónimos (asiento→silla,
                // etc.), insensibles a tildes, contra el índice search_text.
                // Todos al INICIO de palabra: ver wordStartRegex.
                $variants = static::cleanVariants(\App\Services\System\SearchSynonyms::expand($tok));
                $norm = static::sanitizeSqlLiteral(
                    \App\Services\System\MarketplaceListingSyncService::normalizeForSearch($tok)
                );
                $w->where(function ($sub) use ($tok, $variants, $norm) {
                    foreach ($variants as $v) {
                        $sub->orWhereRaw('search_text REGEXP ?', [static::wordStartRegex($v)]);
                    }
                    if ($norm !== '' && static::allowsInnerMatch($norm)) {
                        $sub->orWhere('search_text', 'like', '%' . $norm . '%');
                    }
                    // Fallback: columnas originales (también al inicio de
                    // palabra) + SKU/código interno, que sí casa a mitad.
                    // Nombre y SKU de las VARIANTES (activas y con stock), en vivo:
                    // `search_text` se corta a 500 caracteres y no lleva SKU, así
                    // que una variante «Rojo» o el SKU «PVC-T1-04» no encontraban
                    // al producto. Solo grafías (plural/erratas), no sinónimos:
                    // «asiento» no debe traer variantes de «silla».
                    $grafiasVar = static::cleanVariants(\App\Services\System\SearchSynonyms::spellings($tok));
                    $tokLike = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $tok);
                    $sub->orWhereExists(function ($ex) use ($grafiasVar, $tokLike) {
                        $ex->select(\DB::raw(1))
                            ->from('marketplace_listing_variants as lvs')
                            ->whereColumn('lvs.listing_id', 'marketplace_listings.id')
                            ->where('lvs.is_active', true)
                            ->where('lvs.stock', '>', 0)
                            ->where(function ($m) use ($grafiasVar, $tokLike) {
                                foreach ($grafiasVar as $v) {
                                    $m->orWhere('lvs.display_name', 'like', $v . '%')
                                      ->orWhere('lvs.display_name', 'like', '% ' . $v . '%');
                                }
                                // SKU: a mitad desde 3 caracteres; con 2, solo prefijo.
                                $m->orWhere('lvs.sku', 'like', (mb_strlen($tokLike) >= 3 ? '%' : '') . $tokLike . '%');
                            });
                    });
                    $sub->orWhere('title', 'like', $tok . '%')
                        ->orWhere('title', 'like', '% ' . $tok . '%')
                        ->orWhere('category_name', 'like', $tok . '%')
                        ->orWhere('category_name', 'like', '% ' . $tok . '%')
                        ->orWhere('brand_name', 'like', $tok . '%')
                        ->orWhere('internal_id', 'like', '%' . $tok . '%');
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
     * Video promocional publicado de este producto, o null. Público = aprobado
     * por el SuperAdmin y con el extra de la tienda vigente (PromoVideo::publico).
     */
    public function promoVideo(): ?PromoVideo
    {
        if (!$this->client_id || !$this->remote_item_id) {
            return null;
        }

        return PromoVideo::publico()
            ->where('client_id', $this->client_id)
            ->where('remote_item_id', $this->remote_item_id)
            ->first();
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
