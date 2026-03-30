<?php

namespace App\Listeners;

use App\Events\ThemeChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Limpia cachés relacionadas al theme cuando una empresa cambia de theme.
 */
class ClearThemeCache
{
    public function handle(ThemeChanged $event): void
    {
        // Limpiar caché del theme anterior
        if ($event->oldThemeId) {
            Cache::forget("theme_record_{$event->oldThemeId}");
        }

        // Limpiar caché del nuevo
        if ($event->newThemeId) {
            Cache::forget("theme_record_{$event->newThemeId}");
        }

        // Limpiar caché de configuración del tenant
        Cache::forget("tenant_config_ecommerce_{$event->tenantUuid}");

        Log::info("Theme cache cleared for tenant {$event->tenantUuid}", [
            'old_theme' => $event->oldThemeId,
            'new_theme' => $event->newThemeId,
        ]);
    }
}
