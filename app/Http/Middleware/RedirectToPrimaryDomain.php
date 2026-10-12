<?php

namespace App\Http\Middleware;

use Closure;
use Hyn\Tenancy\Contracts\CurrentHostname;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Http\Request;

/**
 * Una tienda, una direccion: manda al dominio principal del tenant.
 *
 * EL PROBLEMA QUE RESUELVE
 * ------------------------
 * En cuanto un tenant tiene dominio propio pasa a ser alcanzable por DOS
 * direcciones —`grupoalasitas.com` y `alasitas.ebaemy.com`— y las dos sirven
 * exactamente el mismo catalogo, cada pagina declarandose canonica de si
 * misma, porque la canonical se construye con `url()->current()`.
 *
 * Es el mismo duplicado que ya tenemos entre la tienda y el marketplace
 * central, repetido ahora dentro del propio tenant, y con el agravante de que
 * seria autoinfligido justo al activar la funcion que pretende que la tienda
 * se indexe mejor.
 *
 * Las columnas `is_primary` y `redirect_to_primary` existian en `hostnames`
 * desde el principio y **nadie las leia**: se guardaban y no hacian nada.
 *
 * CUANDO ACTUA
 * ------------
 * Solo si se cumple todo: hay tenant resuelto, el host de la peticion no es
 * el principal, ese host tiene `redirect_to_primary`, y el website tiene un
 * principal marcado. Mientras nadie marque un principal —hoy son 0 de 16— no
 * hace absolutamente nada.
 *
 * Se queda fuera lo que no debe redirigirse nunca: las peticiones que no son
 * GET (un POST redirigido pierde el cuerpo), las de API y las que esperan
 * JSON.
 */
class RedirectToPrimaryDomain
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->isMethod('GET') || $request->expectsJson() || $request->is('api*')) {
            return $next($request);
        }

        try {
            $actual = app(CurrentHostname::class);
        } catch (\Throwable $e) {
            return $next($request);
        }

        if (!$actual || !$actual->website_id || $actual->is_primary) {
            return $next($request);
        }

        if (!$actual->redirect_to_primary) {
            return $next($request);
        }

        $principal = Hostname::where('website_id', $actual->website_id)
            ->where('is_primary', true)
            ->first();

        // Sin principal marcado no hay a donde ir. Y si por lo que sea el
        // principal es este mismo host, se deja pasar: mejor servir la pagina
        // que entrar en un bucle de redirecciones.
        if (!$principal || strcasecmp($principal->fqdn, $actual->fqdn) === 0) {
            return $next($request);
        }

        return redirect()->away(
            'https://' . $principal->fqdn . $request->getRequestUri(),
            301
        );
    }
}
