<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Tenant\Configuration;
use App\Helpers\UserControlHelper;


class LockedTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // La configuracion se lee de cache; cuando la cache esta fria toca la
        // BD del tenant, y si en ese punto la conexion 'tenant' todavia no
        // esta registrada la consulta tira InvalidArgumentException y el
        // storefront responde 500 a TODOS los visitantes.
        //
        // Paso 2026-08-28: un optimize:clear vacio la cache que venia tapando
        // esto desde hacia meses y cuatro tiendas quedaron caidas.
        //
        // Ante la duda se deja pasar y se registra: no poder leer el candado
        // no es razon para tumbar la tienda. Un tenant bloqueado que sirva
        // paginas por unos segundos es mucho menos grave que un 500 para
        // todos, y en cuanto la conexion este lista el candado vuelve a
        // aplicarse solo.
        try {
            $configuration = Configuration::firstCached() ?: new Configuration();
        } catch (\Throwable $e) {
            \Log::warning('LockedTenant: no se pudo leer la configuracion del tenant', [
                'host'  => $request->getHost(),
                'error' => $e->getMessage(),
            ]);

            return $next($request);
        }

        if($configuration->isLockedTenant()){
            abort(403);
        }

        UserControlHelper::checkActiveUser();

        return $next($request);
    }
}
