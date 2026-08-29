<?php

namespace App\Services;

use App\Models\Tenant\ConfigurationEcommerce;

/**
 * EcommerceThemeTokens — Paleta completa del ecommerce del tenant como
 * variables CSS, calculada server-side para que no haya flash de color.
 *
 * Antes de esto cada layout repetía el mismo bloque de conversión HEX→HSL
 * (master, layout_ecommerce_cart, layout_ecommerce_item, record y el theme
 * ropa: 5 copias del mismo código) y emitía SOLO --primary-h/s/l. Los themes
 * que necesitaban más color lo escribían a mano — por eso el header del theme
 * tecnologia tiene #0f172a hardcodeado y sale oscuro para todo tenant.
 *
 * Reglas de diseño:
 *  - ADITIVO: --primary-h/s/l se siguen emitiendo igual que siempre. Todo el
 *    CSS existente (styles_ecommerce.css, themes/*.css) sigue funcionando sin
 *    tocarse. Los tokens --theme-* son nuevos y nadie los pisa.
 *  - Solo el primario se DERIVA (hover, soft, contraste). El resto de la
 *    paleta tiene defaults fijos y sensatos que el tenant puede sobreescribir.
 *    Derivar toda la paleta del primario suena elegante y produce combinaciones
 *    feas cuando el tenant elige un amarillo o un rosa.
 *  - El texto sobre primario/header/footer se elige por luminancia: un tenant
 *    con primario amarillo recibe texto oscuro, no blanco ilegible.
 *
 * Persistencia: preferences['theme_colors'] en configuration_ecommerce.
 * OJO: quien guarde preferences debe hacer MERGE, no reemplazo
 * (ver ConfigurationController::mergePreferences).
 */
class EcommerceThemeTokens
{
    /** Primario histórico del sistema cuando el tenant nunca eligió color. */
    public const FALLBACK_PRIMARY = '#ff8000';

    /**
     * Defaults de la paleta. Claves = las que acepta preferences['theme_colors'].
     * 'primary' no está aquí: sale de configuration_ecommerce.color_ecommerce,
     * que es el campo que el tenant ya venía usando.
     */
    public const DEFAULTS = [
        'secondary'      => '#334155',
        'accent'         => '#0ea5e9',
        'background'     => '#ffffff',
        'surface'        => '#f8fafc',
        'text_primary'   => '#0f172a',
        'text_secondary' => '#64748b',
        'border'         => '#e2e8f0',
        'header'         => '#ffffff',
        'footer'         => '#0f172a',
        'offer'          => '#dc2626',
        'success'        => '#16a34a',
        'danger'         => '#dc2626',
    ];

    /**
     * Presets listos. El tenant elige uno y se copia a sus colores; a partir
     * de ahí puede editar cualquiera. 'primary' incluido porque un preset que
     * no toca el color principal no se siente como un preset.
     */
    public const PRESETS = [
        'tecnologia_oscuro' => [
            'label'  => 'Tecnología oscuro',
            'colors' => [
                'primary'        => '#6366f1',
                'secondary'      => '#1e293b',
                'accent'         => '#22d3ee',
                'background'     => '#ffffff',
                'surface'        => '#f1f5f9',
                'text_primary'   => '#0f172a',
                'text_secondary' => '#64748b',
                'border'         => '#e2e8f0',
                'header'         => '#0f172a',
                'footer'         => '#0f172a',
                'offer'          => '#f43f5e',
                'success'        => '#16a34a',
                'danger'         => '#dc2626',
            ],
        ],
        'tecnologia_azul' => [
            'label'  => 'Tecnología azul',
            'colors' => [
                'primary'        => '#2563eb',
                'secondary'      => '#1e3a8a',
                'accent'         => '#38bdf8',
                'background'     => '#ffffff',
                'surface'        => '#f0f6ff',
                'text_primary'   => '#0f172a',
                'text_secondary' => '#5b6b82',
                'border'         => '#dbe6f5',
                'header'         => '#ffffff',
                'footer'         => '#1e3a8a',
                'offer'          => '#e11d48',
                'success'        => '#16a34a',
                'danger'         => '#dc2626',
            ],
        ],
        'tecnologia_moderno' => [
            'label'  => 'Tecnología moderno',
            'colors' => [
                'primary'        => '#111827',
                'secondary'      => '#374151',
                'accent'         => '#84cc16',
                'background'     => '#ffffff',
                'surface'        => '#f5f5f4',
                'text_primary'   => '#111827',
                'text_secondary' => '#6b7280',
                'border'         => '#e5e7eb',
                'header'         => '#ffffff',
                'footer'         => '#111827',
                'offer'          => '#ea580c',
                'success'        => '#16a34a',
                'danger'         => '#dc2626',
            ],
        ],
        'corporativo' => [
            'label'  => 'Corporativo',
            'colors' => [
                'primary'        => '#0f766e',
                'secondary'      => '#134e4a',
                'accent'         => '#f59e0b',
                'background'     => '#ffffff',
                'surface'        => '#f6faf9',
                'text_primary'   => '#0f172a',
                'text_secondary' => '#5f6f75',
                'border'         => '#dfe8e7',
                'header'         => '#ffffff',
                'footer'         => '#134e4a',
                'offer'          => '#dc2626',
                'success'        => '#16a34a',
                'danger'         => '#dc2626',
            ],
        ],
    ];

    /**
     * Paleta resuelta del tenant actual: color_ecommerce + overrides.
     *
     * @return array<string,string> claves de DEFAULTS más 'primary'
     */
    public static function palette(?ConfigurationEcommerce $config = null): array
    {
        try {
            $config = $config ?: ConfigurationEcommerce::firstCached();
        } catch (\Throwable $e) {
            $config = null;
        }

        $primary = self::normalizeHex($config->color_ecommerce ?? null) ?: self::FALLBACK_PRIMARY;

        $overrides = [];
        try {
            $prefs = $config->preferences ?? [];
            if (is_array($prefs) && is_array($prefs['theme_colors'] ?? null)) {
                $overrides = $prefs['theme_colors'];
            }
        } catch (\Throwable $e) {
            // Sin overrides: la paleta por defecto es válida por sí sola.
        }

        $palette = ['primary' => $primary];
        foreach (self::DEFAULTS as $key => $default) {
            $palette[$key] = self::normalizeHex($overrides[$key] ?? null) ?: $default;
        }

        return $palette;
    }

    /**
     * Bloque `:root{...}` listo para meter en un <style>. Sin la etiqueta:
     * el partial que lo imprime decide dónde va.
     */
    public static function cssVariables(?ConfigurationEcommerce $config = null): string
    {
        $p   = self::palette($config);
        $hsl = self::toHsl($p['primary']);

        $vars = [
            // ── LEGACY: no tocar. Todo el CSS actual depende de estas tres. ──
            '--primary-h' => $hsl['h'],
            '--primary-s' => $hsl['s'] . '%',
            '--primary-l' => $hsl['l'] . '%',

            // ── Paleta nueva ──
            '--theme-primary'          => $p['primary'],
            '--theme-primary-hover'    => self::shade($p['primary'], -12),
            '--theme-primary-soft'     => self::mixWithWhite($p['primary'], 88),
            '--theme-primary-contrast' => self::readableOn($p['primary']),
            '--theme-secondary'        => $p['secondary'],
            '--theme-accent'           => $p['accent'],
            '--theme-background'       => $p['background'],
            '--theme-surface'          => $p['surface'],
            '--theme-text-primary'     => $p['text_primary'],
            '--theme-text-secondary'   => $p['text_secondary'],
            '--theme-border'           => $p['border'],
            '--theme-header'           => $p['header'],
            '--theme-header-text'      => self::readableOn($p['header']),
            // Escalones del header para barras internas (nav, buscador). Se
            // calculan aquí en vez de con color-mix() en el CSS: color-mix()
            // no existe en navegadores anteriores a 2023 y ahí la declaración
            // entera se descarta, dejando la barra transparente.
            '--theme-header-soft'      => self::towardsContrast($p['header'], 8),
            '--theme-header-soft-2'    => self::towardsContrast($p['header'], 18),
            '--theme-header-line'      => self::hairlineOn($p['header']),
            '--theme-footer'           => $p['footer'],
            '--theme-footer-text'      => self::readableOn($p['footer']),
            '--theme-footer-line'      => self::hairlineOn($p['footer']),
            '--theme-offer'            => $p['offer'],
            '--theme-offer-contrast'   => self::readableOn($p['offer']),
            '--theme-success'          => $p['success'],
            '--theme-danger'           => $p['danger'],
        ];

        $out = '';
        foreach ($vars as $name => $value) {
            $out .= $name . ':' . $value . ';';
        }

        return ':root{' . $out . '}';
    }

    // ── Utilidades de color ───────────────────────────────────────────────

    /**
     * '#abc' / 'abc' / '#aabbcc' → '#aabbcc'. Null si no es un hex válido
     * (así el caller cae a su default en vez de emitir CSS roto).
     */
    public static function normalizeHex($value): ?string
    {
        if (!is_string($value)) return null;
        $hex = ltrim(trim($value), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) return null;

        return '#' . strtolower($hex);
    }

    /** @return array{r:int,g:int,b:int} */
    public static function toRgb(string $hex): array
    {
        $hex = ltrim(self::normalizeHex($hex) ?: '#000000', '#');

        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * HEX → HSL redondeado. Misma fórmula que traían los 5 bloques inline,
     * para que --primary-h/s/l salgan idénticos a antes.
     *
     * @return array{h:int,s:int,l:int}
     */
    public static function toHsl(string $hex): array
    {
        ['r' => $r, 'g' => $g, 'b' => $b] = self::toRgb($hex);
        $r /= 255; $g /= 255; $b /= 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l   = ($max + $min) / 2;

        if ($max == $min) {
            $h = $s = 0;
        } else {
            $d = $max - $min;
            $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
            if ($max == $r)      $h = ($g - $b) / $d + ($g < $b ? 6 : 0);
            elseif ($max == $g)  $h = ($b - $r) / $d + 2;
            else                 $h = ($r - $g) / $d + 4;
            $h /= 6;
        }

        return [
            'h' => (int) round($h * 360),
            's' => (int) round($s * 100),
            'l' => (int) round($l * 100),
        ];
    }

    /**
     * Aclara (+) u oscurece (−) un hex en N puntos de luminosidad.
     */
    public static function shade(string $hex, int $deltaL): string
    {
        $hsl = self::toHsl($hex);
        $l   = max(0, min(100, $hsl['l'] + $deltaL));

        return self::hslToHex($hsl['h'], $hsl['s'], $l);
    }

    /**
     * Mezcla con blanco: $whitePct=88 devuelve un tinte muy claro, útil como
     * fondo de badges y estados hover sin pedirle otro color al tenant.
     */
    public static function mixWithWhite(string $hex, int $whitePct): string
    {
        $whitePct = max(0, min(100, $whitePct));
        $c = self::toRgb($hex);
        $f = $whitePct / 100;

        return sprintf(
            '#%02x%02x%02x',
            (int) round($c['r'] + (255 - $c['r']) * $f),
            (int) round($c['g'] + (255 - $c['g']) * $f),
            (int) round($c['b'] + (255 - $c['b']) * $f)
        );
    }

    /**
     * Texto legible sobre un fondo: blanco o casi-negro según la luminancia
     * relativa (WCAG). Sin esto, un tenant con header amarillo obtiene texto
     * blanco sobre amarillo.
     */
    public static function readableOn(string $hex): string
    {
        return self::luminance($hex) > 0.45 ? '#111827' : '#ffffff';
    }

    /**
     * Acerca un color a su propio color de contraste en $pct.
     * Un header oscuro se aclara, uno claro se oscurece: así la barra de
     * navegación siempre se distingue del header sin importar qué eligió el
     * tenant. Mezclar siempre con blanco solo funciona en themes oscuros.
     */
    public static function towardsContrast(string $hex, int $pct): string
    {
        $pct = max(0, min(100, $pct));
        $c   = self::toRgb($hex);
        $t   = self::luminance($hex) > 0.45 ? 0 : 255;
        $f   = $pct / 100;

        return sprintf(
            '#%02x%02x%02x',
            (int) round($c['r'] + ($t - $c['r']) * $f),
            (int) round($c['g'] + ($t - $c['g']) * $f),
            (int) round($c['b'] + ($t - $c['b']) * $f)
        );
    }

    /** Línea divisoria de bajo contraste sobre un fondo dado. */
    public static function hairlineOn(string $hex): string
    {
        return self::towardsContrast($hex, 22);
    }

    /** Luminancia relativa WCAG, 0 (negro) a 1 (blanco). */
    public static function luminance(string $hex): float
    {
        $c = self::toRgb($hex);
        $ch = [];
        foreach (['r', 'g', 'b'] as $k) {
            $v = $c[$k] / 255;
            $ch[$k] = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
        }

        return 0.2126 * $ch['r'] + 0.7152 * $ch['g'] + 0.0722 * $ch['b'];
    }

    public static function hslToHex(int $h, int $s, int $l): string
    {
        $h = (($h % 360) + 360) % 360;
        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        if     ($h < 60)  { $r = $c; $g = $x; $b = 0;  }
        elseif ($h < 120) { $r = $x; $g = $c; $b = 0;  }
        elseif ($h < 180) { $r = 0;  $g = $c; $b = $x; }
        elseif ($h < 240) { $r = 0;  $g = $x; $b = $c; }
        elseif ($h < 300) { $r = $x; $g = 0;  $b = $c; }
        else              { $r = $c; $g = 0;  $b = $x; }

        return sprintf(
            '#%02x%02x%02x',
            (int) round(($r + $m) * 255),
            (int) round(($g + $m) * 255),
            (int) round(($b + $m) * 255)
        );
    }
}
