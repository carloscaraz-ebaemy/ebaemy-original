<?php

namespace App\Services;

use App\Models\Tenant\ConfigurationEcommerce;

/**
 * EcommerceHomeSections — Orden y encendido de las secciones del home.
 *
 * Antes las secciones estaban cableadas en orden fijo dentro de index.blade.php.
 * Aquí viven como catálogo; el blade solo itera lo que devuelve resolve().
 *
 * Dos reglas de diseño que importan:
 *
 *  1. ZONAS. El home tiene dos contenedores distintos en el DOM: las secciones
 *     del catálogo van dentro de la columna .ecommerce-view, y las de abajo
 *     (garantías, reseñas, vistos) cuelgan directo del .row. Mover una sección
 *     de zona le cambia el ancho y el padding, así que el orden se aplica
 *     DENTRO de cada zona. Preservar el DOM exacto es lo que garantiza que un
 *     tenant que nunca tocó esta pantalla vea su home igual que siempre.
 *
 *  2. CATÁLOGO GANA SOBRE LO GUARDADO. Lo guardado es solo el orden y los
 *     apagados; las secciones nuevas que se agreguen al catálogo aparecen
 *     solas en su posición por defecto. Si en vez de eso guardáramos la lista
 *     completa, cada tenant quedaría congelado con el catálogo del día que
 *     guardó y nunca vería una sección nueva.
 */
class EcommerceHomeSections
{
    public const ZONE_MAIN  = 'main';   // dentro de .col-lg-12.ecommerce-view
    public const ZONE_WIDE  = 'wide';   // hijo directo de .row

    /**
     * Catálogo. El orden de este array ES el orden por defecto.
     *
     *  partial   vista a incluir (namespace ecommerce::)
     *  zone      contenedor del DOM, ver nota 1
     *  locked            no se puede apagar (el catálogo es el home)
     *  hide_on_category  se omite al filtrar por categoría
     *  hide_on_tag       se omite al filtrar por tag
     *
     * Las dos últimas replican exactamente las condiciones que tenía el blade:
     * el banner también desaparecía al filtrar por tag, las demás no.
     */
    public const CATALOG = [
        'slider' => [
            'label'     => 'Banner principal',
            'hint'      => 'Carrusel de promociones',
            'partial'   => 'ecommerce::layouts.partials_ecommerce.home_slider',
            'zone'             => self::ZONE_MAIN,
            'hide_on_category' => true,
            'hide_on_tag'      => true,
        ],
        'flash_sale' => [
            'label'     => 'Ofertas flash',
            'hint'      => 'Cuenta regresiva de la promoción activa',
            'partial'          => 'ecommerce::layouts.partials_ecommerce.flash_sale',
            'zone'             => self::ZONE_MAIN,
            'hide_on_category' => true,
        ],
        'bundles' => [
            'label'     => 'Paquetes',
            'hint'      => 'Combos y conjuntos de productos',
            'partial'          => 'ecommerce::layouts.partials_ecommerce.bundles',
            'zone'             => self::ZONE_MAIN,
            'hide_on_category' => true,
        ],
        'products' => [
            'label'   => 'Catálogo de productos',
            'hint'    => 'La grilla con filtros. No se puede ocultar.',
            'partial' => 'ecommerce::layouts.partials_ecommerce.home_products',
            'zone'    => self::ZONE_MAIN,
            'locked'  => true,
        ],
        'offers' => [
            'label'     => 'Ofertas especiales',
            'hint'      => 'Banners promocionales debajo del catálogo',
            'partial'          => 'ecommerce::layouts.partials_ecommerce.home_offers',
            'zone'             => self::ZONE_MAIN,
            'hide_on_category' => true,
        ],
        'trust' => [
            'label'   => 'Garantías de compra',
            'hint'    => 'Envío, compra segura, devoluciones, atención',
            'partial' => 'ecommerce::layouts.partials_ecommerce.home_trust_badges',
            'zone'    => self::ZONE_WIDE,
        ],
        'testimonials' => [
            'label'   => 'Opiniones de clientes',
            'hint'    => 'Reseñas reales aprobadas. Se oculta sola con menos de 3.',
            'partial' => 'ecommerce::layouts.partials_ecommerce.home_testimonials',
            'zone'    => self::ZONE_WIDE,
        ],
        'recently_viewed' => [
            'label'   => 'Vistos recientemente',
            'hint'    => 'Últimos productos que abrió el visitante',
            'partial' => 'ecommerce::layouts.partials_ecommerce.home_recently_viewed',
            'zone'    => self::ZONE_WIDE,
        ],
    ];

    /** Clave dentro de configuration_ecommerce.preferences */
    public const PREF_KEY = 'home_sections';

    /**
     * Secciones a renderizar en una zona, ya ordenadas y filtradas.
     *
     * @param string $zone        ZONE_MAIN | ZONE_WIDE
     * @param bool   $hasCategory  el home está filtrado por categoría
     * @param bool   $hasTag       el home está filtrado por tag
     * @return array<int,array{key:string,partial:string}>
     */
    public static function forZone(string $zone, bool $hasCategory = false, bool $hasTag = false): array
    {
        $out = [];
        foreach (self::resolve() as $key => $enabled) {
            $meta = self::CATALOG[$key];
            if ($meta['zone'] !== $zone) continue;
            if (!$enabled) continue;
            if ($hasCategory && ($meta['hide_on_category'] ?? false)) continue;
            if ($hasTag && ($meta['hide_on_tag'] ?? false)) continue;

            $out[] = ['key' => $key, 'partial' => $meta['partial']];
        }

        return $out;
    }

    /**
     * Estado resuelto: [clave => bool encendida], en orden de render.
     *
     * @return array<string,bool>
     */
    public static function resolve(?ConfigurationEcommerce $config = null): array
    {
        $saved = self::saved($config);
        $order = $saved['order'] ?? [];
        $off   = $saved['disabled'] ?? [];

        // Primero lo guardado, respetando su orden; después todo lo que el
        // catálogo tenga y el tenant nunca haya visto (secciones nuevas).
        $keys = [];
        foreach ($order as $key) {
            if (isset(self::CATALOG[$key]) && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }
        foreach (array_keys(self::CATALOG) as $key) {
            if (!in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        $result = [];
        foreach ($keys as $key) {
            $locked = self::CATALOG[$key]['locked'] ?? false;
            $result[$key] = $locked ? true : !in_array($key, $off, true);
        }

        return $result;
    }

    /**
     * Catálogo listo para la pantalla de configuración: etiqueta, ayuda,
     * estado y si se puede apagar, en el orden actual del tenant.
     */
    public static function forEditor(?ConfigurationEcommerce $config = null): array
    {
        $out = [];
        foreach (self::resolve($config) as $key => $enabled) {
            $meta = self::CATALOG[$key];
            $out[] = [
                'key'     => $key,
                'label'   => $meta['label'],
                'hint'    => $meta['hint'],
                'zone'    => $meta['zone'],
                'locked'  => (bool) ($meta['locked'] ?? false),
                'enabled' => $enabled,
            ];
        }

        return $out;
    }

    /**
     * Normaliza lo que manda la pantalla de configuración antes de guardarlo.
     * Descarta claves desconocidas y nunca deja apagada una sección locked.
     *
     * @param array $payload  ['order' => [...], 'disabled' => [...]]
     */
    public static function sanitize(array $payload): array
    {
        $order = [];
        foreach ((array) ($payload['order'] ?? []) as $key) {
            if (isset(self::CATALOG[$key]) && !in_array($key, $order, true)) {
                $order[] = $key;
            }
        }

        $disabled = [];
        foreach ((array) ($payload['disabled'] ?? []) as $key) {
            if (!isset(self::CATALOG[$key])) continue;
            if (self::CATALOG[$key]['locked'] ?? false) continue;
            if (!in_array($key, $disabled, true)) {
                $disabled[] = $key;
            }
        }

        return ['order' => $order, 'disabled' => $disabled];
    }

    /** Lo que hay guardado, o array vacío si el tenant nunca tocó esto. */
    protected static function saved(?ConfigurationEcommerce $config = null): array
    {
        try {
            $config = $config ?: ConfigurationEcommerce::firstCached();
            $prefs  = $config->preferences ?? [];
            if (is_array($prefs) && is_array($prefs[self::PREF_KEY] ?? null)) {
                return $prefs[self::PREF_KEY];
            }
        } catch (\Throwable $e) {
            // Sin configuración: el orden por defecto del catálogo es válido.
        }

        return [];
    }
}
