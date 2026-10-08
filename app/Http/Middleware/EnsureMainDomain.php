<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * El dominio principal (marketplace + SuperAdmin) solo responde en SU host.
 *
 * EL AGUJERO QUE CIERRA
 * ---------------------
 * `routes/web.php` registra las rutas del tenant o las del sistema segun si el
 * host de la peticion esta en `hostnames`. El grupo del sistema se declaraba
 * con `Route::domain($prefix . env('APP_URL_BASE'))`, pero **con la
 * configuracion cacheada `env()` no lee el .env y devuelve null** — y
 * `Route::domain(null)` no restringe nada.
 *
 * Resultado, comprobado en produccion el 2026-10-07: CUALQUIER subdominio que
 * no sea de un tenant servia el marketplace central entero, con su
 * `<link rel="canonical">` apuntandose a si mismo. Y como el DNS es
 * `*.ebaemy.com`, eso incluye los inventados: `noexiste-xyz123.ebaemy.com`
 * devolvia 200 con el marketplace. Tambien subdominios de tiendas que ya no
 * existen (`myka`, `torneo`, `torneoperu`), que es por donde lo vio el usuario.
 *
 * POR QUE UN MIDDLEWARE Y NO ARREGLAR `Route::domain()`
 * -----------------------------------------------------
 * `Route::domain()` acepta UN host, y aqui hay que dejar pasar al menos dos
 * (`ebaemy.com` y `www.ebaemy.com`, que hoy responde 200 y no se puede
 * romper). Registrar el grupo una vez por host duplicaria ~900 KB de tabla de
 * rutas en cada peticion, porque en este proyecto las rutas NO se pueden
 * cachear (ver RouteCacheProhibida).
 *
 * Un host desconocido se manda al principal con 301 en vez de 404: conserva el
 * enlace, consolida el SEO en una sola URL y es lo que espera quien teclea un
 * subdominio viejo.
 */
class EnsureMainDomain
{
    /** Hosts de desarrollo y de llamadas internas de la propia maquina. */
    private const SIEMPRE_PERMITIDOS = ['localhost', '127.0.0.1', '::1'];

    public function handle(Request $request, Closure $next)
    {
        $base = (string) config('app.url');
        $main = strtolower((string) parse_url($base, PHP_URL_HOST));

        // Sin saber cual es el dominio principal no se bloquea nada: es
        // preferible el agujero conocido a tumbar el sitio por una config
        // incompleta.
        if ($main === '') return $next($request);

        $host = strtolower($request->getHost());

        $permitidos = array_merge(
            [$main, 'www.' . $main],
            self::SIEMPRE_PERMITIDOS,
            (array) config('app.extra_main_hosts', [])
        );

        if (in_array($host, $permitidos, true)) return $next($request);

        // Guarda contra el bucle: si por lo que sea el destino es este mismo
        // host, se deja pasar en vez de redirigir a si mismo para siempre.
        if ($host === $main) return $next($request);

        return redirect()->away(
            rtrim($base, '/') . $request->getRequestUri(),
            301
        );
    }
}
