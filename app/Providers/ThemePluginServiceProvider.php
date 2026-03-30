<?php

namespace App\Providers;

use App\Services\ThemePluginService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class ThemePluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemePluginService::class, fn() => new ThemePluginService());
        $this->app->alias(ThemePluginService::class, 'theme.plugins');
    }

    public function boot(): void
    {
        // @pluginCss — genera CSS links
        Blade::directive('pluginCss', function () {
            return "<?php echo app('theme.plugins')->renderCss(); ?>";
        });

        // @pluginJs — genera JS scripts
        Blade::directive('pluginJs', function () {
            return "<?php echo app('theme.plugins')->renderJs(); ?>";
        });

        // @hasRubroFeature('size_selector') ... @endhasRubroFeature
        Blade::if('hasRubroFeature', function (string $feature) {
            return app('theme.plugins')->hasRubroFeature($feature);
        });
    }
}
