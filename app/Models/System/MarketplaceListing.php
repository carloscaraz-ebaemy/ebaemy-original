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
    public const STOPWORDS = [
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

        // Medidas a su forma canonica ANTES de partir: «2,40 m», «2.40m» y
        // «240 cm» son el token «240cm», igual que en el indice. Va primero
        // porque la coma decimal partiria «2,40» en dos palabras.
        $q = \App\Services\System\SearchMeasures::normalize($q);

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
     * Grafias de un token (SearchSynonyms::spellings) MAS sus formas de
     * medida (SearchMeasures::alternatives: «2.40» -> «240cm»). Es lo que
     * usan filtro, orden y la eleccion de variante, para que los tres
     * coincidan.
     *
     * @return array<int,string>
     */
    public static function tokenSpellings(string $tok): array
    {
        return array_values(array_unique(array_merge(
            \App\Services\System\SearchSynonyms::spellings($tok),
            \App\Services\System\SearchMeasures::alternatives($tok)
        )));
    }

    /**
     * tokenSpellings MAS los sinonimos de significado (SearchSynonyms::expand).
     *
     * @return array<int,string>
     */
    public static function tokenExpansions(string $tok): array
    {
        return array_values(array_unique(array_merge(
            \App\Services\System\SearchSynonyms::expand($tok),
            \App\Services\System\SearchMeasures::alternatives($tok)
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
        $catTokens = [];
        foreach (array_slice($tokens, 0, 5) as $tok) {
            $grafias = static::cleanVariants(static::tokenSpellings($tok));
            $sinonimos = array_values(array_diff(
                static::cleanVariants(static::tokenExpansions($tok)),
                $grafias
            ));
            $plain = static::sanitizeSqlLiteral($tok);
            if (empty($grafias)) continue;

            // Para la bonificación de CATEGORÍA (abajo): esta palabra, con
            // grafías y sinónimos, al inicio de alguna palabra del nombre de
            // la categoría. LIKE y no REGEXP porque la collation ignora las
            // tildes («arbol» casa con «Árboles») y las grafías van sin ellas.
            $catTokens[] = static::categoryMatchSql(array_merge($grafias, $sinonimos));

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

        // +4 cuando TODAS las palabras buscadas están en el nombre de la
        // categoría (de la tienda u oficial, con su padre). Buscando «árboles
        // navideños», los pinos de «Árboles de Navidad» van antes que la
        // «Canasta de árbol navideño», aunque esta tenga las dos palabras en
        // el título: la categoría dice QUÉ ES el producto; el título, cómo se
        // llama. Vale lo que un escalón de título (6 vs 2) para que gane.
        if ($catTokens) {
            $terms[] = '(CASE WHEN ' . implode(' AND ', $catTokens) . ' THEN 4 ELSE 0 END)';
        }

        return empty($terms) ? null : '(' . implode(' + ', $terms) . ')';
    }

    /**
     * Condición SQL: alguna palabra del nombre de la categoría empieza por
     * una de $variantes. Mira `category_name` (la de la tienda) y, por
     * subconsulta, la categoría oficial y su padre. LIKE y no REGEXP porque
     * la collation ignora tildes y mayúsculas («arbol» casa con «Árboles»)
     * y las variantes vienen ya sin tildes.
     *
     * @param array<int,string> $variantes ya saneadas (cleanVariants)
     */
    protected static function categoryMatchSql(array $variantes): string
    {
        $likes = fn (string $col) => implode(' OR ', array_merge(...array_map(
            fn ($v) => ["{$col} LIKE '{$v}%'", "{$col} LIKE '% {$v}%'"],
            $variantes
        )));

        return '(' . $likes('marketplace_listings.category_name')
            . ' OR EXISTS (SELECT 1 FROM marketplace_categories mc'
            . ' LEFT JOIN marketplace_categories pc ON pc.id = mc.parent_id'
            . ' WHERE mc.id = marketplace_listings.marketplace_category_id'
            . ' AND (' . $likes("CONCAT_WS(' ', mc.name, pc.name)") . ')))';
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
     * Máximo de palabras que entran en la búsqueda flexible. Cada una suma un
     * CASE con sus variantes; más allá de esto la consulta crece sin que el
     * orden cambie de verdad.
     */
    private const LOOSE_MAX_TOKENS = 8;

    /**
     * Condición SQL de UN token: verdadera si el producto lo contiene. Es la
     * pieza de scopeSearch (todas a la vez) y de la búsqueda flexible (cuenta
     * cuántas casan).
     *
     * Casa si alguna de estas cosas empieza por la palabra (o una de sus
     * grafías, sinónimos o formas de medida):
     *  - una palabra del índice `search_text` (al inicio: ver wordStartRegex);
     *  - el índice a mitad de palabra, solo si allowsInnerMatch;
     *  - el nombre o el SKU de una VARIANTE activa con stock, en vivo:
     *    `search_text` tiene un largo máximo y no lleva SKU, así que una
     *    variante «Rojo» o el SKU «PVC-T1-04» no encontraban al producto. Solo
     *    grafías (plural/erratas), no sinónimos: «asiento» no debe traer
     *    variantes de «silla»;
     *  - título, categoría o marca tal cual, y el código interno a mitad.
     *
     * Todo va INLINE, sin bindings, por dos motivos:
     *  1. La búsqueda flexible reutiliza esta condición en el WHERE, en el
     *     window function del SELECT y en el ORDER BY; duplicar bindings
     *     rompe el COUNT de la paginación (ver textRelevanceSql).
     *  2. Un REGEXP con el patrón como parámetro de un prepared statement
     *     se recompila en CADA fila en MySQL 8: medido en local, 566 ms
     *     contra 4 ms del mismo patrón inline sobre 300 filas. Con 4 o 5
     *     patrones por palabra, cada búsqueda tardaba más de 2 s en SQL.
     *
     * Lo que se interpola es seguro: las variantes pasan por
     * sanitizeSqlLiteral (letras, dígitos, espacio, guion) y lo que se
     * compara tal cual se cita con PDO::quote, que es lo mismo que hacía el
     * binding.
     */
    public static function tokenMatchSql(string $tok): string
    {
        $pdo   = (new static)->getConnection()->getPdo();
        $quote = fn (string $v) => $pdo->quote($v);

        $variants = static::cleanVariants(static::tokenExpansions($tok));
        $norm = static::sanitizeSqlLiteral(
            \App\Services\System\MarketplaceListingSyncService::normalizeForSearch($tok)
        );

        $or = [];
        foreach ($variants as $v) {
            $or[] = "search_text REGEXP '" . static::wordStartRegex($v) . "'";
        }
        if ($norm !== '' && static::allowsInnerMatch($norm)) {
            $or[] = "search_text LIKE '%{$norm}%'";
        }

        $grafiasVar = static::cleanVariants(static::tokenSpellings($tok));
        $tokLike = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $tok);
        $varOr = [];
        foreach ($grafiasVar as $v) {
            $varOr[] = "lvs.display_name LIKE '{$v}%'";
            $varOr[] = "lvs.display_name LIKE '% {$v}%'";
        }
        // SKU: a mitad desde 3 caracteres; con 2, solo prefijo.
        $varOr[] = 'lvs.sku LIKE ' . $quote((mb_strlen($tokLike) >= 3 ? '%' : '') . $tokLike . '%');
        $or[] = 'EXISTS (SELECT 1 FROM marketplace_listing_variants AS lvs'
            . ' WHERE lvs.listing_id = marketplace_listings.id'
            . ' AND lvs.is_active = 1 AND lvs.stock > 0'
            . ' AND (' . implode(' OR ', $varOr) . '))';

        $or[] = 'title LIKE ' . $quote($tok . '%');
        $or[] = 'title LIKE ' . $quote('% ' . $tok . '%');
        $or[] = 'category_name LIKE ' . $quote($tok . '%');
        $or[] = 'category_name LIKE ' . $quote('% ' . $tok . '%');
        $or[] = 'brand_name LIKE ' . $quote($tok . '%');
        $or[] = 'internal_id LIKE ' . $quote('%' . $tok . '%');

        return '(' . implode(' OR ', $or) . ')';
    }

    /**
     * Búsqueda tolerante: divide la query en palabras y exige que CADA
     * palabra aparezca (ver tokenMatchSql). Asi 'x 24' encuentra
     * 'x24 Hojas', 'planta artificial' encuentra 'planta de bambu artificial',
     * etc. Sin la division, una sola palabra inexacta hace 0 resultados.
     *
     * Si esto da cero, el llamante puede probar scopeSearchLoose.
     */
    public function scopeSearch($query, ?string $q)
    {
        // Tokenizar: cada palabra >=2 chars se exige presente. Tokens cortos
        // (1 char) suelen ser ruido — los ignoramos a menos que sea el unico.
        $tokens = static::searchTokens($q);
        if (empty($tokens)) return $query;

        return $query->where(function ($w) use ($tokens) {
            foreach ($tokens as $tok) {
                $w->whereRaw(static::tokenMatchSql($tok));
            }
        });
    }

    /**
     * Palabras con contenido que cuentan en la búsqueda flexible: las de
     * searchTokens sin las de relleno, hasta LOOSE_MAX_TOKENS.
     *
     * @return array<int,string>
     */
    public static function looseTokens(?string $q): array
    {
        $relleno = array_flip(self::STOPWORDS);
        $tokens = array_values(array_filter(
            static::searchTokens($q),
            fn ($t) => !isset($relleno[\App\Services\System\MarketplaceListingSyncService::normalizeForSearch($t)])
        ));

        return array_slice($tokens, 0, self::LOOSE_MAX_TOKENS);
    }

    /**
     * Cuántas palabras tienen que casar en la búsqueda flexible: la mitad de
     * las palabras con contenido, redondeando hacia arriba, y nunca 0. Null
     * si no hay búsqueda flexible posible: con una sola palabra «la mitad» es
     * esa misma palabra, y la búsqueda exacta ya dijo que no está.
     */
    public static function looseMinMatches(?string $q): ?int
    {
        $n = count(static::looseTokens($q));

        return $n >= 2 ? max(1, (int) ceil($n / 2)) : null;
    }

    /**
     * Expresión SQL con CUÁNTAS palabras de la búsqueda casan con el
     * producto (0..n). Sirve para filtrar la búsqueda flexible y para
     * ordenarla: primero el que acierta más palabras. Inline, como
     * textRelevanceSql, para poder repetirla en SELECT y ORDER BY.
     */
    public static function matchCountSql(?string $q): ?string
    {
        $tokens = static::looseTokens($q);
        if (count($tokens) < 2) return null;

        $partes = array_map(
            fn ($tok) => '(CASE WHEN ' . static::tokenMatchSql($tok) . ' THEN 1 ELSE 0 END)',
            $tokens
        );

        return '(' . implode(' + ', $partes) . ')';
    }

    /**
     * Búsqueda FLEXIBLE: la segunda pasada cuando la exacta (todas las
     * palabras) no encontró nada. Acepta productos que tengan al menos la
     * mitad de las palabras con contenido (looseMinMatches). «arbol pino
     * verde» trae los «Pino Americano» aunque ninguno diga «verde».
     *
     * El llamante ordena por matchCountSql DESC y avisa al comprador de que
     * no son resultados exactos. Sin búsqueda flexible posible (una sola
     * palabra) no devuelve nada: no hay «parecido» que mostrar.
     */
    public function scopeSearchLoose($query, ?string $q)
    {
        $min   = static::looseMinMatches($q);
        $count = static::matchCountSql($q);
        if ($min === null || $count === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw("{$count} >= {$min}");
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
