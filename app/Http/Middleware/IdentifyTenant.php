<?php

namespace App\Http\Middleware;

use App\Services\TenantManager;
use Closure;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Http\Request;

/**
 * IdentifyTenant — Resuelve, valida y configura el tenant.
 *
 * Complementa hyn/multi-tenant auto-identification con:
 *  - Validación de estado (suspendido, trial expirado)
 *  - Redirect al dominio principal (redirect_to_primary)
 *  - Configuración de timezone/locale
 *  - Soporte X-Tenant-ID header para APIs
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        /** @var TenantManager $manager */
        $manager = app(TenantManager::class);

        // hyn ya resolvió el tenant. Solo validamos y configuramos.
        if (!$manager->check()) {
            $host = $request->header('X-Tenant-ID') ?: $request->getHost();
            if ($host && !$manager->resolveFromHost($host)) {
                abort(404, 'Empresa no encontrada');
            }
        }

        // Redirect al dominio principal
        $redirect = $this->checkRedirectToPrimary($request);
        if ($redirect) {
            return $redirect;
        }

        // Validar estado
        if ($manager->check() && !$manager->isActive()) {
            $state = $manager->state();
            match ($state) {
                TenantManager::STATE_SUSPENDED     => abort(403, 'Cuenta suspendida. Contacte al administrador.'),
                TenantManager::STATE_TRIAL_EXPIRED => abort(403, 'Período de prueba expirado.'),
                default => null,
            };
        }

        // Timezone y locale
        $this->applyTenantConfig($manager);

        return $next($request);
    }

    /**
     * Si el hostname tiene redirect_to_primary=true, redirigir al dominio principal.
     */
    protected function checkRedirectToPrimary(Request $request): ?\Illuminate\Http\RedirectResponse
    {
        try {
            $host = strtolower($request->getHost());
            $hostname = Hostname::where('fqdn', $host)->first();

            if (!$hostname || !($hostname->redirect_to_primary ?? false)) {
                return null;
            }

            // Buscar el hostname principal del mismo website
            $primary = Hostname::where('website_id', $hostname->website_id)
                ->where('is_primary', true)
                ->where('id', '!=', $hostname->id)
                ->first();

            if (!$primary) {
                return null;
            }

            $scheme = $request->isSecure() ? 'https' : 'http';
            $url = $scheme . '://' . $primary->fqdn . $request->getRequestUri();

            return redirect($url, 301);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function applyTenantConfig(TenantManager $manager): void
    {
        if (!$manager->check()) return;

        $tz = $manager->config('timezone');
        if ($tz) {
            config(['app.timezone' => $tz]);
            date_default_timezone_set($tz);
        }

        $locale = $manager->config('locale');
        if ($locale) {
            app()->setLocale($locale);
        }
    }
}
