<?php

namespace App\Http\Middleware;

use App\Services\ThemeManager;
use App\Services\ThemePluginService;
use Closure;
use Illuminate\Http\Request;

/**
 * SetTheme — Resuelve el theme y prepone sus vistas al namespace 'ecommerce'.
 *
 * Corre DESPUÉS de que hyn/multi-tenant resuelva el tenant.
 * Inyecta los paths del theme activo al namespace 'ecommerce::' para que
 * view('ecommerce::index') busque primero en el theme, luego en el módulo original.
 *
 * Soporta preview via ?_theme=ropa (solo usuarios autenticados).
 */
class SetTheme
{
    public function handle(Request $request, Closure $next)
    {
        try {
            /** @var ThemeManager $manager */
            $manager = app(ThemeManager::class);

            // Preview via query param
            $preview = $request->query('_theme');
            if ($preview && auth()->check()) {
                $manager->setTheme($preview);
            } else {
                $manager->boot();
            }

            // Inyectar paths al namespace 'ecommerce::'
            $this->injectThemePaths($manager);

            // Exponer en config para acceso rápido desde vistas
            $activeTheme = $manager->getActiveTheme();

            config([
                'app.active_theme'     => $activeTheme,
                'app.theme_css_path'   => $manager->getThemeCssPath(),
                'app.is_default_theme' => $manager->isDefault(),
            ]);

            // Establecer contexto de plugins
            $this->setPluginContext($request, $activeTheme);
        } catch (\Throwable $e) {
            // Si falla, la app sigue con las vistas originales del módulo
            \Log::warning('SetTheme: ' . $e->getMessage());
        }

        return $next($request);
    }

    /**
     * Prepone view paths del theme al namespace 'ecommerce'.
     *
     * Resultado:
     *   view('ecommerce::index') busca en:
     *     1. themes/{activo}/index.blade.php
     *     2. themes/default/index.blade.php
     *     3. modules/Ecommerce/.../index.blade.php (original)
     */
    /**
     * Establecer contexto de plugins (theme, página, rubro).
     */
    protected function setPluginContext(Request $request, string $theme): void
    {
        try {
            $pluginService = app(ThemePluginService::class);

            // Detectar página
            $path = $request->path();
            $page = 'other';
            if (str_contains($path, 'ecommerce/item/')) $page = 'product';
            elseif (str_contains($path, 'ecommerce/category')) $page = 'category';
            elseif (preg_match('#^ecommerce/?$#', $path)) $page = 'home';
            elseif (str_contains($path, 'detail_cart')) $page = 'cart';
            elseif (str_contains($path, 'checkout')) $page = 'checkout';

            // Detectar rubro desde el theme
            $rubroMap = [
                'ropa' => 'ropa', 'ropa-urbana' => 'ropa-urbana', 'ropa-elegante' => 'ropa-elegante',
                'tecnologia' => 'tecnologia', 'alimentos' => 'alimentos', 'deportes' => 'deportes',
                'lujo' => 'lujo', 'farmacia' => 'farmacia', 'ferreteria' => 'ferreteria',
            ];
            $rubro = $rubroMap[$theme] ?? null;

            $pluginService->setContext($theme, $page, $rubro);
        } catch (\Throwable $e) {
            // No bloquear si falla
        }
    }

    protected function injectThemePaths(ThemeManager $manager): void
    {
        $paths = $manager->getViewPaths();
        if (empty($paths)) return;

        $finder = app('view')->getFinder();
        $hints  = $finder->getHints();

        // Namespace 'ecommerce': prepone theme paths antes de los originales
        $original = $hints['ecommerce'] ?? [];
        $hints['ecommerce'] = array_values(array_unique(array_merge($paths, $original)));

        // Namespace 'theme': para vistas exclusivas del theme
        $hints['theme'] = $paths;

        $ref = new \ReflectionProperty($finder, 'hints');
        $ref->setAccessible(true);
        $ref->setValue($finder, $hints);
        $finder->flush();
    }
}
