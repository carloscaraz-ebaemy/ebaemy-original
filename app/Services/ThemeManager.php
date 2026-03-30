<?php

namespace App\Services;

use App\Models\System\Theme;
use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Support\Facades\Cache;

/**
 * ThemeManager — Resuelve, activa y gestiona el theme del tenant.
 *
 * Resolución en cascada:
 *  1. theme_id directo (FK a tabla themes del sistema)
 *  2. ecommerce_mode=nicho + business_type
 *  3. theme_template legacy (mapeo estático)
 *  4. Fallback a 'default'
 *
 * Funcionalidades:
 *  - View paths con fallback automático (theme activo → default → módulo)
 *  - Merge de settings (defaults del theme + personalizaciones del tenant)
 *  - Assets del theme (CSS, JS, imágenes)
 *  - Caché por tenant para evitar resoluciones repetidas
 */
class ThemeManager
{
    protected ?string $activeTheme = null;
    protected ?Theme $themeRecord = null;
    protected bool $booted = false;
    protected array $settings = [];

    public const DEFAULT_THEME = 'default';
    public const THEMES_BASE_DIR = 'themes';

    /** Mapeo legacy: theme_template → carpeta */
    protected static array $templateMap = [
        'generic'  => 'default',
        'fashion'  => 'ropa',
        'food'     => 'alimentos',
        'tech'     => 'tecnologia',
        'sports'   => 'deportes',
        'luxury'   => 'lujo',
        'pharmacy' => 'farmacia',
        'hardware' => 'ferreteria',
    ];

    /**
     * Inicializar: resolver theme para el tenant actual.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->activeTheme = $this->resolve();
        $this->booted = true;
    }

    /**
     * Resolver theme con cascada de prioridades.
     */
    protected function resolve(): string
    {
        try {
            $config = ConfigurationEcommerce::firstCached();
        } catch (\Throwable $e) {
            return self::DEFAULT_THEME;
        }

        // P1: theme_id directo
        if ($themeId = ($config->theme_id ?? null)) {
            $name = $this->resolveFromId($themeId);
            if ($name) return $name;
        }

        // P2: modo nicho + business_type
        if (($config->ecommerce_mode ?? 'general') === 'nicho') {
            $bt = $config->business_type ?? null;
            if ($bt && $this->pathExists($bt)) {
                return $bt;
            }
        }

        // P3: theme_template legacy
        $template = $config->theme_template ?? 'generic';
        $mapped = self::$templateMap[$template] ?? $template;
        if ($mapped !== self::DEFAULT_THEME && $this->pathExists($mapped)) {
            return $mapped;
        }

        return self::DEFAULT_THEME;
    }

    /**
     * Resolver desde ID de la tabla themes (con caché).
     */
    protected function resolveFromId(int $id): ?string
    {
        try {
            $theme = Cache::remember("theme_record_{$id}", 600, function () use ($id) {
                return Theme::where('id', $id)->where('is_active', true)->first();
            });

            if ($theme && $this->pathExists($theme->path)) {
                $this->themeRecord = $theme;
                return $theme->path;
            }
        } catch (\Throwable $e) {
            // Tabla puede no existir pre-migración
        }
        return null;
    }

    /**
     * Forzar un theme (para preview).
     */
    public function setTheme(string $theme): void
    {
        $this->activeTheme = $this->pathExists($theme) ? $theme : self::DEFAULT_THEME;
        $this->booted = true;
    }

    /**
     * Theme activo.
     */
    public function getActiveTheme(): string
    {
        return $this->activeTheme ?? self::DEFAULT_THEME;
    }

    /**
     * ¿Es el default?
     */
    public function isDefault(): bool
    {
        return $this->getActiveTheme() === self::DEFAULT_THEME;
    }

    /**
     * Registro de la tabla themes (si se resolvió por ID).
     */
    public function getThemeRecord(): ?Theme
    {
        return $this->themeRecord;
    }

    /**
     * View paths ordenados: theme activo → default (para inyectar en namespace).
     *
     * @return string[]
     */
    public function getViewPaths(): array
    {
        $base  = resource_path('views/' . self::THEMES_BASE_DIR);
        $paths = [];
        $active = $this->getActiveTheme();

        if ($active !== self::DEFAULT_THEME) {
            $p = $base . '/' . $active;
            if (is_dir($p)) $paths[] = $p;
        }

        $d = $base . '/' . self::DEFAULT_THEME;
        if (is_dir($d)) $paths[] = $d;

        return $paths;
    }

    /**
     * ¿Existe la carpeta de un theme?
     */
    public function pathExists(string $theme): bool
    {
        return is_dir(resource_path('views/' . self::THEMES_BASE_DIR . '/' . $theme));
    }

    /**
     * CSS path del theme para cargar en el layout.
     */
    public function getThemeCssPath(): ?string
    {
        try {
            $config = ConfigurationEcommerce::firstCached();
            $template = $config->theme_template ?? 'generic';
        } catch (\Throwable $e) {
            return null;
        }

        if ($template === 'generic') return null;

        $file = "porto-light/css/themes/{$template}.css";
        return file_exists(public_path($file)) ? $file : null;
    }

    /**
     * Asset path del theme.
     * Busca en: public/themes/{active}/ → public/themes/default/
     *
     * @param string $path Ruta relativa (ej: "css/custom.css")
     * @return string URL del asset
     */
    public function asset(string $path): string
    {
        $active = $this->getActiveTheme();

        // Buscar en theme activo
        $themePath = "themes/{$active}/{$path}";
        if (file_exists(public_path($themePath))) {
            return asset($themePath);
        }

        // Fallback a default
        if ($active !== self::DEFAULT_THEME) {
            $defaultPath = "themes/" . self::DEFAULT_THEME . "/{$path}";
            if (file_exists(public_path($defaultPath))) {
                return asset($defaultPath);
            }
        }

        // Fallback absoluto
        return asset($path);
    }

    /**
     * Obtener setting del theme con merge.
     * Prioridad: personalización del tenant → default del theme.
     *
     * @param string $key     Clave del setting (dot notation)
     * @param mixed  $default Valor por defecto
     * @return mixed
     */
    public function setting(string $key, $default = null)
    {
        // Cargar settings si no están en memoria
        if (empty($this->settings)) {
            $this->loadSettings();
        }

        return data_get($this->settings, $key, $default);
    }

    /**
     * Cargar settings: merge de defaults del theme + personalizaciones.
     */
    protected function loadSettings(): void
    {
        // Defaults del theme (desde archivo de configuración del theme)
        $active = $this->getActiveTheme();
        $configFile = resource_path("views/themes/{$active}/theme.json");
        $defaults = [];
        if (file_exists($configFile)) {
            $defaults = json_decode(file_get_contents($configFile), true) ?? [];
        }

        // Personalizaciones del tenant (desde BD)
        $custom = [];
        try {
            $econfig = ConfigurationEcommerce::firstCached();
            $prefs = $econfig->preferences ?? [];
            if (is_array($prefs)) {
                $custom = $prefs;
            }
        } catch (\Throwable $e) {
            // Sin personalización
        }

        // Merge: custom sobreescribe defaults
        $this->settings = array_replace_recursive($defaults, $custom);
    }

    /**
     * Todos los themes disponibles (carpetas que existen).
     *
     * @return array<string, array>
     */
    public function getAvailableThemes(): array
    {
        $base = resource_path('views/' . self::THEMES_BASE_DIR);
        $themes = [];

        if (!is_dir($base)) return $themes;

        foreach (new \DirectoryIterator($base) as $dir) {
            if ($dir->isDot() || !$dir->isDir()) continue;
            $name = $dir->getFilename();
            $themes[$name] = [
                'name'       => $name,
                'path'       => $dir->getPathname(),
                'is_default' => $name === self::DEFAULT_THEME,
                'is_active'  => $name === $this->getActiveTheme(),
            ];
        }

        return $themes;
    }

    /**
     * Registrar nuevo mapeo template → theme (extensible por plugins).
     */
    public static function registerTemplate(string $key, string $folder): void
    {
        self::$templateMap[$key] = $folder;
    }

    /** Mapeo completo. */
    public static function getTemplateMap(): array
    {
        return self::$templateMap;
    }
}
