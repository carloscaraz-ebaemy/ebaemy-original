<?php

namespace App\Services;

use App\Models\Tenant\ConfigurationEcommerce;

/**
 * EcommerceCardOptions — Qué muestra la tarjeta de producto.
 *
 * La tarjeta traía favoritos, comparador, vista rápida y badges siempre
 * encendidos, y el comparador se ocultaba en móvil con un display:none
 * global en el layout. No todos los rubros quieren lo mismo: una tienda de
 * tecnología vive del comparador, una de comida no lo usa nunca.
 *
 * Todo default es TRUE salvo lo que antes no existía (marca, rating), para
 * que un tenant que no toque nada vea su tarjeta igual que siempre.
 *
 * Persiste en preferences['card_options']. Solo se guardan las opciones que
 * difieren del default — ver ConfigurationController::store_card_options.
 */
class EcommerceCardOptions
{
    public const PREF_KEY = 'card_options';

    /**
     * Catálogo de opciones.
     *
     *  default  valor cuando el tenant no configuró nada
     *  group    para agrupar en la pantalla de configuración
     */
    public const CATALOG = [
        // ── Acciones ──
        'wishlist' => [
            'label'   => 'Favoritos',
            'hint'    => 'Corazón para guardar el producto',
            'default' => true,
            'group'   => 'Acciones',
        ],
        'compare' => [
            'label'   => 'Comparar',
            'hint'    => 'Comparador de productos lado a lado',
            'default' => true,
            'group'   => 'Acciones',
        ],
        'quickview' => [
            'label'   => 'Vista rápida',
            'hint'    => 'Ver el producto sin salir del listado',
            'default' => true,
            'group'   => 'Acciones',
        ],
        'add_to_cart' => [
            'label'   => 'Botón de carrito',
            'hint'    => 'Agregar al carrito desde el listado. Apagado, la tarjeta lleva a la ficha.',
            'default' => true,
            'group'   => 'Acciones',
        ],
        'compare_mobile' => [
            'label'   => 'Comparador en celular',
            'hint'    => 'El comparador es incómodo en pantallas chicas',
            'default' => false,
            'group'   => 'Acciones',
        ],

        // ── Información ──
        'brand' => [
            'label'   => 'Marca',
            'hint'    => 'Encima del nombre del producto',
            'default' => false,
            'group'   => 'Información',
        ],
        'category' => [
            'label'   => 'Categoría',
            'hint'    => 'Etiqueta de categoría en la tarjeta',
            'default' => true,
            'group'   => 'Información',
        ],
        'rating' => [
            'label'   => 'Calificación',
            'hint'    => 'Estrellas y cantidad de reseñas aprobadas',
            'default' => false,
            'group'   => 'Información',
        ],
        'old_price' => [
            'label'   => 'Precio anterior',
            'hint'    => 'Precio tachado cuando hay descuento',
            'default' => true,
            'group'   => 'Información',
        ],
        'discount_pct' => [
            'label'   => 'Porcentaje de descuento',
            'hint'    => 'Badge -35% sobre la imagen',
            'default' => false,
            'group'   => 'Información',
        ],

        // ── Badges ──
        'badge_new' => [
            'label'   => 'Badge "Nuevo"',
            'hint'    => 'Productos creados en los últimos 30 días',
            'default' => true,
            'group'   => 'Badges',
        ],
        'badge_stock' => [
            'label'   => 'Badge de stock bajo',
            'hint'    => '"Últimas 3" cuando quedan 5 o menos',
            'default' => true,
            'group'   => 'Badges',
        ],
        'badge_variants' => [
            'label'   => 'Badge "Variantes"',
            'hint'    => 'Marca los productos con opciones',
            'default' => true,
            'group'   => 'Badges',
        ],
    ];

    /** Cache por request: la tarjeta pregunta una vez por producto. */
    protected static ?array $resolved = null;

    /**
     * Estado de una opción. Es lo que llama la tarjeta:
     *   @if(card_option('compare')) ... @endif
     */
    public static function enabled(string $key): bool
    {
        if (!isset(self::CATALOG[$key])) {
            return false;
        }

        return self::all()[$key] ?? (bool) self::CATALOG[$key]['default'];
    }

    /**
     * Todas las opciones resueltas: [clave => bool].
     *
     * @return array<string,bool>
     */
    public static function all(?ConfigurationEcommerce $config = null): array
    {
        if ($config === null && self::$resolved !== null) {
            return self::$resolved;
        }

        $saved = [];
        try {
            $config = $config ?: ConfigurationEcommerce::firstCached();
            $prefs  = $config->preferences ?? [];
            if (is_array($prefs) && is_array($prefs[self::PREF_KEY] ?? null)) {
                $saved = $prefs[self::PREF_KEY];
            }
        } catch (\Throwable $e) {
            // Sin configuración: los defaults son válidos por sí solos.
        }

        $out = [];
        foreach (self::CATALOG as $key => $meta) {
            $out[$key] = array_key_exists($key, $saved)
                ? (bool) $saved[$key]
                : (bool) $meta['default'];
        }

        if ($config === null || self::$resolved === null) {
            self::$resolved = $out;
        }

        return $out;
    }

    /**
     * Catálogo para la pantalla de configuración, agrupado.
     */
    public static function forEditor(?ConfigurationEcommerce $config = null): array
    {
        $state  = self::all($config);
        $groups = [];

        foreach (self::CATALOG as $key => $meta) {
            $groups[$meta['group']][] = [
                'key'     => $key,
                'label'   => $meta['label'],
                'hint'    => $meta['hint'],
                'enabled' => $state[$key],
            ];
        }

        $out = [];
        foreach ($groups as $name => $options) {
            $out[] = ['group' => $name, 'options' => $options];
        }

        return $out;
    }

    /**
     * Normaliza el payload de la pantalla: solo claves conocidas, y solo las
     * que difieren del default. Guardar únicamente las diferencias permite
     * cambiar un default más adelante sin que cada tenant quede congelado
     * con una copia del default viejo.
     */
    public static function sanitize(array $payload): array
    {
        $clean = [];
        foreach (self::CATALOG as $key => $meta) {
            if (!array_key_exists($key, $payload)) continue;

            $value = filter_var($payload[$key], FILTER_VALIDATE_BOOLEAN);
            if ($value !== (bool) $meta['default']) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /** Solo para tests: olvida la resolución cacheada del request. */
    public static function flush(): void
    {
        self::$resolved = null;
    }
}
