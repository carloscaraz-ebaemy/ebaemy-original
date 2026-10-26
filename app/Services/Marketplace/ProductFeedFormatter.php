<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceCategory;

/**
 * Lo que el feed /feeds/meta-catalog.xml necesita limpiar antes de mandarlo a
 * Google Merchant Center, Meta y TikTok. Tres problemas medidos el 2026-10-24
 * en producción (271 items):
 *
 *  1. 116 descripciones llegaban con «&nbsp;» escrito: el texto viene del
 *     editor con entidades HTML, strip_tags() quita las etiquetas pero no
 *     traduce las entidades, y al escapar para XML quedaba «&amp;nbsp;» —
 *     que el anuncio muestra tal cual.
 *  2. 123 items sin marca y 123 con «OEM» (más «GENERICO»). Meta exige marca y
 *     Google, sin ella, limita el producto. Sin marca real se usa el nombre de
 *     la tienda: es quien lo vende y es lo que el comprador reconoce.
 *  3. Ningún item llevaba google_product_category. Se resuelve desde la
 *     categoría oficial del marketplace con config/marketplace_google_categories.php.
 */
class ProductFeedFormatter
{
    /** Marcas que no son marca: se tratan como «sin marca». */
    private const NO_MARCA = [
        'oem', 'generico', 'generica', 'generic', 'sin marca', 'sinmarca', 's/m', 'sm',
        'ninguna', 'ninguno', 'n/a', 'na', 'no aplica', 'no brand', 'otros', 'otra',
        'varios', 'varias', '-', '.', '0',
    ];

    /** @var array<int,string>|null id de categoría → full_slug, una consulta por petición */
    private ?array $slugs = null;

    /**
     * Texto plano para título o descripción: sin etiquetas, sin entidades HTML,
     * sin espacios duros ni saltos repetidos.
     */
    public static function text(?string $html, int $max = 5000): string
    {
        $s = (string) $html;
        // Que los bloques no se peguen al quitar las etiquetas, ni al abrir ni al
        // cerrar («Uno</p><p>Dos», «Idiomas<p>Inglés»).
        $s = preg_replace('~<\s*/?\s*(br|p|li|ul|ol|div|h[1-6]|tr|td|th)\b[^>]*>~i', ' ', $s);
        $s = strip_tags($s);
        // Dos pasadas: hay textos guardados ya escapados una vez («&amp;nbsp;»).
        $s = html_entity_decode(html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace("\u{00A0}", ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return mb_substr(trim($s), 0, $max);
    }

    /**
     * Marca para el feed: la real si es una marca; si no, el nombre de la tienda.
     */
    public static function brand(?string $brand, ?string $store): ?string
    {
        $b = trim(self::text($brand, 70));
        $clave = mb_strtolower(strtr($b, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u']));
        if ($b !== '' && !in_array($clave, self::NO_MARCA, true)) {
            return $b;
        }

        $tienda = trim(self::text($store, 70));
        if ($tienda === '') {
            return null;
        }
        // «floristería pétalo & encanto» → «Floristería Pétalo & Encanto».
        return $tienda === mb_strtolower($tienda) ? mb_convert_case($tienda, MB_CASE_TITLE, 'UTF-8') : $tienda;
    }

    /**
     * ID de la taxonomía de Google para una categoría oficial del marketplace,
     * por el prefijo de full_slug más largo del mapa. null si no aplica.
     */
    public function googleCategory(?int $categoryId): ?int
    {
        if (!$categoryId) {
            return null;
        }
        if ($this->slugs === null) {
            $this->slugs = MarketplaceCategory::query()->pluck('full_slug', 'id')->all();
        }

        return self::resolve($this->slugs[$categoryId] ?? null, config('marketplace_google_categories', []));
    }

    /**
     * Envío para el feed, a partir de la política que confirmó la tienda
     * (marketplace_listings.shipping). Solo lo que es cierto para TODO el
     * país, porque Google no admite regiones de Perú en el atributo por item:
     *
     *  - <g:shipping> precio 0 solo si la tienda envía gratis a Lima Y provincias.
     *  - <g:min/max_handling_time>: los días hábiles que tarda en despachar.
     *
     * Los costos por zona (Lima S/10, provincias…) van en la configuración de
     * envío de la cuenta de Merchant Center, no aquí. Sin política → nada: el
     * feed no inventa un envío.
     */
    public static function shippingXml(?array $s): string
    {
        if (!$s) {
            return '';
        }
        $xml = '';
        if (($s['lima']['mode'] ?? null) === 'free' && ($s['province']['mode'] ?? null) === 'free') {
            $xml .= "    <g:shipping>\n      <g:country>PE</g:country>\n      <g:service>Envío gratis</g:service>\n      <g:price>0.00 PEN</g:price>\n    </g:shipping>\n";
        }
        $handling = $s['province']['handling'] ?? null;
        if ($handling !== null) {
            $h = max(0, (int) $handling);
            $xml .= "    <g:min_handling_time>{$h}</g:min_handling_time>\n";
            $xml .= "    <g:max_handling_time>{$h}</g:max_handling_time>\n";
        }

        return $xml;
    }

    /**
     * Categoría raíz del marketplace («hogar», «moda»…) para el custom_label
     * con el que se arman los conjuntos de productos por categoría en Meta.
     */
    public function rootCategory(?int $categoryId): ?string
    {
        if (!$categoryId) {
            return null;
        }
        if ($this->slugs === null) {
            $this->slugs = MarketplaceCategory::query()->pluck('full_slug', 'id')->all();
        }
        $slug = trim((string) ($this->slugs[$categoryId] ?? ''), '/');

        return $slug === '' ? null : explode('/', $slug)[0];
    }

    /**
     * Etiquetas ESTABLES para armar conjuntos de productos y campañas por tienda
     * y por categoría (Meta/Google/TikTok filtran por custom_label):
     *  - custom_label_2 = «tienda_<subdominio>»: no cambia aunque la tienda se
     *    renombre (custom_label_0 lleva el nombre comercial, que sí cambia y
     *    dejaría vacío el conjunto sin avisar).
     *  - custom_label_3 = «cat_<categoría raíz>».
     */
    public function campaignLabelsXml(?string $subdomain, ?int $categoryId): string
    {
        $esc = fn ($s) => htmlspecialchars((string) $s, ENT_XML1);
        $xml = '';
        if ($subdomain) {
            $xml .= '    <g:custom_label_2>' . $esc('tienda_' . mb_strtolower($subdomain)) . "</g:custom_label_2>\n";
        }
        if ($root = $this->rootCategory($categoryId)) {
            $xml .= '    <g:custom_label_3>' . $esc('cat_' . $root) . "</g:custom_label_3>\n";
        }

        return $xml;
    }

    /** @param array<string,int> $map */
    public static function resolve(?string $fullSlug, array $map): ?int
    {
        $slug = trim((string) $fullSlug, '/');
        while ($slug !== '') {
            if (isset($map[$slug])) {
                return (int) $map[$slug];
            }
            $pos = strrpos($slug, '/');
            $slug = $pos === false ? '' : substr($slug, 0, $pos);
        }

        return null;
    }
}
