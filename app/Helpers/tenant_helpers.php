<?php

/**
 * Helpers globales para tenant y theme.
 *
 * Uso:
 *   tenant()                  → TenantManager instance
 *   tenant('name')            → Company->name
 *   tenant_id()               → UUID del tenant
 *   tenant_setting('stock')   → Configuration->stock
 *   tenant_has_feature('x')   → FeatureGate check
 *   theme()                   → ThemeManager instance
 *   theme('active')           → nombre del theme activo
 *   theme_setting('key')      → setting del theme
 *   theme_asset('css/x.css')  → URL del asset del theme
 */

if (!function_exists('tenant')) {
    /**
     * Acceso rápido al TenantManager o a un atributo del company.
     *
     * @param string|null $key Atributo del company (null = retorna TenantManager)
     * @return mixed
     */
    function tenant(?string $key = null)
    {
        $manager = app(\App\Services\TenantManager::class);

        if ($key === null) {
            return $manager;
        }

        return $manager->company($key);
    }
}

if (!function_exists('tenant_id')) {
    /**
     * UUID del tenant actual.
     */
    function tenant_id(): ?string
    {
        return app(\App\Services\TenantManager::class)->id();
    }
}

if (!function_exists('tenant_setting')) {
    /**
     * Setting de Configuration del tenant.
     *
     * @param string $key     Nombre del campo en Configuration
     * @param mixed  $default Valor por defecto
     * @return mixed
     */
    function tenant_setting(string $key, $default = null)
    {
        return app(\App\Services\TenantManager::class)->config($key) ?? $default;
    }
}

if (!function_exists('tenant_has_feature')) {
    /**
     * ¿Tiene el tenant una feature habilitada?
     */
    function tenant_has_feature(string $feature): bool
    {
        return app(\App\Services\TenantManager::class)->hasFeature($feature);
    }
}

if (!function_exists('theme')) {
    /**
     * Acceso rápido al ThemeManager o a un dato del theme.
     *
     * @param string|null $key 'active' para nombre, null para el manager
     * @return mixed
     */
    function theme(?string $key = null)
    {
        $manager = app(\App\Services\ThemeManager::class);

        if ($key === null) {
            return $manager;
        }

        return match ($key) {
            'active', 'name' => $manager->getActiveTheme(),
            'css'            => $manager->getThemeCssPath(),
            'default'        => $manager->isDefault(),
            'record'         => $manager->getThemeRecord(),
            default          => $manager->setting($key),
        };
    }
}

if (!function_exists('theme_setting')) {
    /**
     * Setting del theme con fallback.
     *
     * @param string $key     Clave (dot notation)
     * @param mixed  $default Valor por defecto
     * @return mixed
     */
    function theme_setting(string $key, $default = null)
    {
        return app(\App\Services\ThemeManager::class)->setting($key, $default);
    }
}

if (!function_exists('theme_asset')) {
    /**
     * URL de un asset del theme con fallback al default.
     *
     * @param string $path Ruta relativa (ej: "css/custom.css")
     * @return string
     */
    function theme_asset(string $path): string
    {
        return app(\App\Services\ThemeManager::class)->asset($path);
    }
}
