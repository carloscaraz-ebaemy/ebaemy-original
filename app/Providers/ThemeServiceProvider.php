<?php

namespace App\Providers;

use App\Services\TenantManager;
use App\Services\ThemeManager;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * ThemeServiceProvider — Registra singletons, Blade directives y helpers.
 *
 * Blade directives:
 *   @tenant('name')               → Atributo del company
 *   @tenantId                     → UUID del tenant
 *   @themeName                    → Nombre del theme activo
 *   @themeSetting('key', 'def')   → Setting del theme
 *   @themeAsset('css/custom.css') → URL del asset del theme
 *   @hasFeature('ecommerce')      → Condicional por feature gate
 *   @isTheme('ropa')              → Condicional por theme
 */
class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TenantManager singleton
        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });
        $this->app->alias(TenantManager::class, 'tenant.manager');

        // ThemeManager singleton
        $this->app->singleton(ThemeManager::class, function () {
            return new ThemeManager();
        });
        $this->app->alias(ThemeManager::class, 'theme.manager');
    }

    public function boot(): void
    {
        $this->registerBladeDirectives();
        $this->registerHelpers();
    }

    /**
     * Blade directives para usar en las vistas.
     */
    protected function registerBladeDirectives(): void
    {
        // @tenant('name') → valor del company
        Blade::directive('tenant', function ($expression) {
            return "<?php echo e(tenant({$expression})); ?>";
        });

        // @tenantId → UUID
        Blade::directive('tenantId', function () {
            return "<?php echo e(tenant_id()); ?>";
        });

        // @themeName → nombre del theme activo
        Blade::directive('themeName', function () {
            return "<?php echo e(app(\\App\\Services\\ThemeManager::class)->getActiveTheme()); ?>";
        });

        // @themeSetting('key', 'default')
        Blade::directive('themeSetting', function ($expression) {
            return "<?php echo e(theme_setting({$expression})); ?>";
        });

        // @themeAsset('path')
        Blade::directive('themeAsset', function ($expression) {
            return "<?php echo theme_asset({$expression}); ?>";
        });

        // @hasFeature('ecommerce') ... @endhasFeature
        Blade::if('hasFeature', function (string $feature) {
            return tenant_has_feature($feature);
        });

        // @isTheme('ropa') ... @endisTheme
        Blade::if('isTheme', function (string $theme) {
            return app(ThemeManager::class)->getActiveTheme() === $theme;
        });

        // @isDefaultTheme ... @endisDefaultTheme
        Blade::if('isDefaultTheme', function () {
            return app(ThemeManager::class)->isDefault();
        });
    }

    /**
     * Registrar archivo de helpers globales.
     */
    protected function registerHelpers(): void
    {
        require_once app_path('Helpers/tenant_helpers.php');
    }
}
