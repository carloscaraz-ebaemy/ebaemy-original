<?php

namespace App\Services\Tenant;

/**
 * Precio aproximado del paquete típico de la tienda con la tarifa de Shalom.
 *
 * - Tamaño estándar (sobre … caja L): el precio exacto de la cotización.
 * - Bulto con medidas: regla de carga = lo mayor entre peso × tarifa por kg,
 *   volumen × tarifa por m³ y el mínimo de la ruta (que coincide con la caja
 *   L). Es una lectura de la tarifa que devuelve Shalom, no una fórmula
 *   documentada: el precio final lo fija Shalom al medir y pesar.
 */
class ShalomPackage
{
    /** Tamaño estándar → clave en `prices` de ShalomApi::quote(). */
    public const SIZES = [
        'SOBRE'       => 'Sobre',
        'PAQUETE XXS' => 'XXS',
        'PAQUETE XS'  => 'XS',
        'PAQUETE S'   => 'S',
        'PAQUETE M'   => 'M',
        'PAQUETE L'   => 'L',
    ];

    /** Limpia lo que llega del panel; null = sin paquete típico (se muestra «desde»). */
    public static function normalize($p): ?array
    {
        if (!is_array($p) || empty($p['type'])) {
            return null;
        }
        if ($p['type'] === 'size' && isset(self::SIZES[$p['size'] ?? ''])) {
            return ['type' => 'size', 'size' => $p['size']];
        }
        if ($p['type'] === 'bulto') {
            $n = fn ($k, $max) => max(0, min($max, round((float) ($p[$k] ?? 0), 1)));
            $b = ['type' => 'bulto', 'length' => $n('length', 300), 'width' => $n('width', 300), 'height' => $n('height', 300), 'weight' => $n('weight', 500)];

            return ($b['length'] > 0 && $b['width'] > 0 && $b['height'] > 0) || $b['weight'] > 0 ? $b : null;
        }

        return null;
    }

    /** Texto corto del paquete: «caja L» o «bulto 80×60×60 cm, 30 kg». */
    public static function label(?array $p): ?string
    {
        if (!$p) {
            return null;
        }
        if ($p['type'] === 'size') {
            return $p['size'] === 'SOBRE' ? 'sobre' : 'caja ' . self::SIZES[$p['size']];
        }
        $dim = ($p['length'] && $p['width'] && $p['height'])
            ? rtrim(rtrim(number_format($p['length'], 1), '0'), '.') . '×' . rtrim(rtrim(number_format($p['width'], 1), '0'), '.') . '×' . rtrim(rtrim(number_format($p['height'], 1), '0'), '.') . ' cm'
            : null;
        $kg = $p['weight'] ? rtrim(rtrim(number_format($p['weight'], 1), '0'), '.') . ' kg' : null;

        return 'bulto ' . implode(', ', array_filter([$dim, $kg]));
    }

    /**
     * Precio aproximado para una cotización de ShalomApi::quote().
     * Devuelve null si no hay paquete típico o faltan tarifas.
     */
    public static function estimate(array $quote, ?array $p): ?float
    {
        if (!$p) {
            return null;
        }
        if ($p['type'] === 'size') {
            $v = $quote['prices'][self::SIZES[$p['size']]] ?? null;

            return $v !== null ? (float) $v : null;
        }

        $r = $quote['rates'] ?? [];
        $porKg = (float) ($r['kg'] ?? 0) * (float) $p['weight'];
        $m3 = ($p['length'] * $p['width'] * $p['height']) / 1000000;
        $porVol = (float) ($r['m3'] ?? 0) * $m3;
        $minimo = (float) ($r['min'] ?? 0);
        $v = max($porKg, $porVol, $minimo);

        // Estimado: al sol entero hacia arriba; los céntimos aparentan una
        // precisión que no tiene.
        return $v > 0 ? (float) ceil($v) : null;
    }
}
