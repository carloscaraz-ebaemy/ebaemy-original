<?php

namespace App\Services;

use App\Models\Tenant\ConfigurationEcommerce;
use Modules\Item\Models\Brand;
use Modules\Item\Models\Category;

/**
 * EcommerceHomeContent — Contenido editable de las secciones del home:
 * garantías de compra, categorías destacadas y marcas.
 *
 * Las garantías estaban escritas a mano en index.blade.php, con sus cuatro
 * SVG y sus cuatro textos fijos: todos los tenants prometían "Despacho en
 * 24-48h" aunque no fuera cierto. Categorías destacadas y marcas no existían.
 *
 * Los defaults de garantías son EXACTAMENTE los cuatro que estaban escritos,
 * así un tenant que no toque nada ve lo mismo de siempre.
 *
 * Persiste en preferences['home_content']. Ver EcommerceHomeSections para el
 * orden y el encendido de las secciones que muestran este contenido.
 */
class EcommerceHomeContent
{
    public const PREF_KEY = 'home_content';

    /**
     * Íconos disponibles para las garantías. Se guarda la clave, no el SVG:
     * así el trazo se puede mejorar después sin tocar la configuración de
     * cada tenant.
     */
    public const ICONS = [
        'shield'   => 'Escudo',
        'truck'    => 'Camión',
        'refresh'  => 'Devolución',
        'chat'     => 'Atención',
        'card'     => 'Pago',
        'lock'     => 'Seguridad',
        'gift'     => 'Regalo',
        'star'     => 'Calidad',
        'clock'    => 'Rapidez',
        'tag'      => 'Precio',
    ];

    /** Los cuatro que estaban escritos a mano en el home. */
    public const DEFAULT_BENEFITS = [
        ['icon' => 'shield',  'title' => 'Compra segura',           'text' => 'Datos protegidos con SSL'],
        ['icon' => 'truck',   'title' => 'Envío a todo el Perú',    'text' => 'Despacho en 24-48h'],
        ['icon' => 'refresh', 'title' => 'Garantía de calidad',     'text' => 'Cambios y devoluciones'],
        ['icon' => 'chat',    'title' => 'Atención personalizada',  'text' => 'WhatsApp y correo'],
    ];

    // ── Garantías ─────────────────────────────────────────────────────────

    /**
     * @return array<int,array{icon:string,title:string,text:string}>
     */
    public static function benefits(?ConfigurationEcommerce $config = null): array
    {
        $saved = self::section('benefits', $config);
        if (!is_array($saved) || empty($saved)) {
            return self::DEFAULT_BENEFITS;
        }

        $out = [];
        foreach ($saved as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') continue;   // una garantía sin título no dice nada

            $out[] = [
                'icon'  => isset(self::ICONS[$row['icon'] ?? '']) ? $row['icon'] : 'star',
                'title' => $title,
                'text'  => trim((string) ($row['text'] ?? '')),
            ];
        }

        return $out;
    }

    // ── Categorías destacadas ─────────────────────────────────────────────

    /**
     * Categorías elegidas por el tenant, en su orden, ya resueltas contra la
     * base. Se descartan las que se borraron: una tarjeta que lleva a una
     * categoría inexistente es peor que no mostrarla.
     *
     * @return array<int,array{id:int,name:string,image:?string,url:string,count:int}>
     */
    public static function featuredCategories(?ConfigurationEcommerce $config = null): array
    {
        $cfg = self::section('featured_categories', $config);
        $ids = array_values(array_filter(array_map('intval', (array) ($cfg['ids'] ?? []))));
        if (empty($ids)) {
            return [];
        }

        try {
            $categories = Category::whereIn('id', $ids)
                ->withCount(['items' => fn($q) => $q->where('apply_store', 1)])
                ->get()
                ->keyBy('id');
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($ids as $id) {
            $cat = $categories->get($id);
            if (!$cat) continue;

            $out[] = [
                'id'    => $cat->id,
                'name'  => $cat->name,
                'image' => $cat->image ? asset('storage/uploads/categories/' . $cat->image) : null,
                'url'   => route('tenant.ecommerce.category', ['category' => $cat->id]),
                'count' => (int) ($cat->items_count ?? 0),
            ];
        }

        return $out;
    }

    /** ¿Mostrar la cantidad de productos en cada tarjeta de categoría? */
    public static function featuredCategoriesShowCount(?ConfigurationEcommerce $config = null): bool
    {
        return (bool) (self::section('featured_categories', $config)['show_count'] ?? false);
    }

    // ── Marcas ────────────────────────────────────────────────────────────

    /**
     * Marcas elegidas, con su logo si el tenant subió uno. El logo vive en la
     * configuración de la sección y no en la tabla brands: la marca es un dato
     * del catálogo, el logo es material de la portada.
     *
     * @return array<int,array{id:int,name:string,logo:?string}>
     */
    public static function brands(?ConfigurationEcommerce $config = null): array
    {
        $cfg  = self::section('brands', $config);
        $rows = (array) ($cfg['items'] ?? []);
        if (empty($rows)) {
            return [];
        }

        $ids = array_values(array_filter(array_map(fn($r) => (int) ($r['id'] ?? 0), $rows)));
        if (empty($ids)) {
            return [];
        }

        try {
            $brands = Brand::whereIn('id', $ids)->get()->keyBy('id');
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $brand = $brands->get((int) ($row['id'] ?? 0));
            if (!$brand) continue;

            $logo = trim((string) ($row['logo'] ?? ''));
            $out[] = [
                'id'   => $brand->id,
                'name' => $brand->name,
                'logo' => $logo !== '' ? asset('storage/uploads/brands/' . $logo) : null,
            ];
        }

        return $out;
    }

    // ── Configuración ─────────────────────────────────────────────────────

    /** Todo el bloque, para la pantalla de configuración. */
    public static function forEditor(?ConfigurationEcommerce $config = null): array
    {
        $cfg = self::all($config);

        return [
            'benefits' => self::benefits($config),
            'icons'    => self::ICONS,
            'featured_categories' => [
                'ids'        => array_values(array_map('intval', (array) ($cfg['featured_categories']['ids'] ?? []))),
                'show_count' => (bool) ($cfg['featured_categories']['show_count'] ?? false),
            ],
            'brands' => array_values(array_filter(
                (array) ($cfg['brands']['items'] ?? []),
                fn($r) => !empty($r['id'])
            )),
        ];
    }

    /**
     * Normaliza el payload de la pantalla. Descarta filas vacías y recorta
     * los textos a lo que entra en la tarjeta.
     */
    public static function sanitize(array $payload): array
    {
        $benefits = [];
        foreach ((array) ($payload['benefits'] ?? []) as $row) {
            $title = trim((string) ($row['title'] ?? ''));
            if ($title === '') continue;

            $benefits[] = [
                'icon'  => isset(self::ICONS[$row['icon'] ?? '']) ? $row['icon'] : 'star',
                'title' => mb_substr($title, 0, 60),
                'text'  => mb_substr(trim((string) ($row['text'] ?? '')), 0, 120),
            ];
        }

        $catIds = [];
        foreach ((array) ($payload['featured_categories']['ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $catIds, true)) {
                $catIds[] = $id;
            }
        }

        $brands = [];
        foreach ((array) ($payload['brands'] ?? []) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) continue;
            if (in_array($id, array_column($brands, 'id'), true)) continue;

            $brands[] = [
                'id'   => $id,
                'logo' => basename(trim((string) ($row['logo'] ?? ''))),
            ];
        }

        return [
            'benefits' => $benefits,
            'featured_categories' => [
                'ids'        => $catIds,
                'show_count' => (bool) ($payload['featured_categories']['show_count'] ?? false),
            ],
            'brands' => ['items' => $brands],
        ];
    }

    // ── Interno ───────────────────────────────────────────────────────────

    protected static function all(?ConfigurationEcommerce $config = null): array
    {
        try {
            $config = $config ?: ConfigurationEcommerce::firstCached();
            $prefs  = $config->preferences ?? [];
            if (is_array($prefs) && is_array($prefs[self::PREF_KEY] ?? null)) {
                return $prefs[self::PREF_KEY];
            }
        } catch (\Throwable $e) {
            // Sin configuración: los defaults son válidos por sí solos.
        }

        return [];
    }

    protected static function section(string $key, ?ConfigurationEcommerce $config = null)
    {
        return self::all($config)[$key] ?? [];
    }
}
