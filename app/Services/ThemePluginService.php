<?php

namespace App\Services;

/**
 * ThemePluginService — Gestiona plugins CSS/JS y features por rubro.
 *
 * Uso en Blade:
 *   @pluginCss        → genera <link> tags de CSS
 *   @pluginJs         → genera <script> tags de JS
 *   @hasRubroFeature('size_selector') ... @endhasRubroFeature
 */
class ThemePluginService
{
    protected array $config;
    protected string $currentTheme = 'default';
    protected string $currentPage = 'other';
    protected ?string $currentRubro = null;

    public function __construct()
    {
        $this->config = config('theme-plugins', []);
    }

    public function setContext(string $theme, string $page, ?string $rubro = null): self
    {
        $this->currentTheme = $theme;
        $this->currentPage = $page;
        $this->currentRubro = $rubro;
        return $this;
    }

    /**
     * Generar HTML de CSS links.
     */
    public function renderCss(): string
    {
        $html = '';

        // Core CSS
        foreach ($this->config['core'] ?? [] as $name => $p) {
            if (($p['enabled'] ?? false) && isset($p['css'])) {
                $html .= '<link rel="stylesheet" href="' . $p['css'] . '" data-plugin="' . $name . '">' . "\n";
            }
        }

        // Ecommerce CSS (filtrado por página)
        foreach ($this->config['ecommerce'] ?? [] as $name => $p) {
            if (($p['enabled'] ?? false) && isset($p['css']) && $this->isForPage($p)) {
                $html .= '<link rel="stylesheet" href="' . $p['css'] . '" data-plugin="' . $name . '">' . "\n";
            }
        }

        return $html;
    }

    /**
     * Generar HTML de JS scripts.
     */
    public function renderJs(): string
    {
        $scripts = [];

        // Core JS
        foreach ($this->config['core'] ?? [] as $name => $p) {
            if (($p['enabled'] ?? false) && isset($p['js'])) {
                $scripts[] = ['src' => $p['js'], 'defer' => $p['defer'] ?? false, 'type' => $p['type'] ?? null, 'name' => $name, 'priority' => $p['priority'] ?? 50];
            }
        }

        // Ecommerce JS
        foreach ($this->config['ecommerce'] ?? [] as $name => $p) {
            if (($p['enabled'] ?? false) && isset($p['js']) && $this->isForPage($p)) {
                $scripts[] = ['src' => $p['js'], 'defer' => $p['defer'] ?? false, 'type' => $p['type'] ?? null, 'name' => $name, 'priority' => $p['priority'] ?? 50];
            }
        }

        usort($scripts, fn($a, $b) => $a['priority'] <=> $b['priority']);

        $html = '';
        foreach ($scripts as $s) {
            $attrs = '';
            if ($s['defer']) $attrs .= ' defer';
            if ($s['type']) $attrs .= ' type="' . $s['type'] . '"';
            $html .= '<script src="' . $s['src'] . '"' . $attrs . ' data-plugin="' . $s['name'] . '"></script>' . "\n";
        }

        return $html;
    }

    /**
     * ¿Tiene una feature del rubro activa?
     */
    public function hasRubroFeature(string $feature): bool
    {
        if (!$this->currentRubro) return false;
        $features = $this->config['rubro'][$this->currentRubro] ?? [];
        return $features[$feature] ?? false;
    }

    /**
     * Features del rubro actual.
     */
    public function getRubroFeatures(): array
    {
        if (!$this->currentRubro) return [];
        return $this->config['rubro'][$this->currentRubro] ?? [];
    }

    public function getTheme(): string { return $this->currentTheme; }
    public function getPage(): string { return $this->currentPage; }
    public function getRubro(): ?string { return $this->currentRubro; }

    protected function isForPage(array $plugin): bool
    {
        $pages = $plugin['pages'] ?? ['*'];
        return in_array('*', $pages) || in_array($this->currentPage, $pages);
    }
}
