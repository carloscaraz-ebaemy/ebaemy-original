<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

/**
 * Identifica al comprador del marketplace (auth guard 'marketplace')
 * en cualquier request — incluyendo subdominios de tenant — y lo
 * expone como $marketplaceUser en views Blade.
 *
 * Setup:
 *   - SESSION_DOMAIN=.ebaemy.com en .env (asi la cookie llega a
 *     subdomain.ebaemy.com).
 *   - SESSION_DRIVER=file con storage compartido (misma app Laravel
 *     para system y tenants — Hyn/Tenancy solo cambia DB connection).
 *
 * Comportamiento:
 *   - Anonymous-friendly: NUNCA redirige a login. Si no hay user, el
 *     middleware setea $marketplaceUser=null y continua.
 *   - Refresca last_seen_at del user con throttling (max 1 update/min)
 *     para no inflar updates en cada hit.
 *   - Bloquea acceso si el user esta status=suspended o deleted.
 */
class IdentifyMarketplaceUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::guard('marketplace')->user();

        if ($user) {
            // Si fue suspendido/borrado entre requests, cerramos sesion.
            if (!method_exists($user, 'isActive') || !$user->isActive()) {
                Auth::guard('marketplace')->logout();
                $user = null;
            } else {
                $this->touchLastSeen($user);
            }
        }

        // Disponible en TODAS las views: @if($marketplaceUser) ... @endif
        View::share('marketplaceUser', $user);

        return $next($request);
    }

    /**
     * Update last_seen_at con throttling: solo si paso >60s del ultimo.
     * Evita un UPDATE por cada request del usuario.
     */
    private function touchLastSeen($user): void
    {
        $now = now();
        $last = $user->last_seen_at;
        if ($last && $last->diffInSeconds($now) < 60) return;
        $user->forceFill(['last_seen_at' => $now])->saveQuietly();
    }
}
