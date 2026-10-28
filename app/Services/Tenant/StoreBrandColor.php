<?php

namespace App\Services\Tenant;

/**
 * El color de la tienda (`color_ecommerce`) hecho seguro para la interfaz.
 *
 * Las tiendas eligen lo que quieren, y algunos colores (amarillos, verdes
 * lima) no se leen sobre blanco ni llevan texto blanco encima. Aquí se
 * oscurece lo justo para llegar a contraste AA (4.5:1) con blanco, sin
 * cambiar el tono: el cliente sigue reconociendo el color de su tienda.
 */
class StoreBrandColor
{
    /** Terracota cálido para las tiendas que no eligieron color. */
    public const DEFAULT = '#B4472C';

    /**
     * @return array{accent:string, raw:string}
     *   accent: el color para botones, enlaces y selección (texto blanco encima)
     *   raw:    el color tal como lo eligió la tienda (detalles decorativos)
     */
    public static function palette(?string $hex): array
    {
        $raw = self::normalize($hex) ?? self::DEFAULT;

        [$r, $g, $b] = self::rgb($raw);
        for ($i = 0; $i < 40 && self::contrastWithWhite($r, $g, $b) < 4.5; $i++) {
            $r *= 0.94; $g *= 0.94; $b *= 0.94;
        }

        return [
            'accent' => sprintf('#%02x%02x%02x', (int) round($r), (int) round($g), (int) round($b)),
            'raw'    => $raw,
        ];
    }

    private static function normalize(?string $hex): ?string
    {
        $hex = trim((string) $hex);
        if (preg_match('/^#?([0-9a-f]{3})$/i', $hex, $m)) {
            $hex = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
        }

        return preg_match('/^#?[0-9a-f]{6}$/i', $hex) ? '#' . strtolower(ltrim($hex, '#')) : null;
    }

    private static function rgb(string $hex): array
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    private static function contrastWithWhite(float $r, float $g, float $b): float
    {
        $lin = function (float $c) {
            $c /= 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        };
        $l = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return 1.05 / ($l + 0.05);
    }
}
