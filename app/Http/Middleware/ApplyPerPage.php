<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * «Filas por página» para todas las tablas del sistema.
 *
 * Casi todos los listados paginan con `config('tenant.items_per_page')` (172
 * sitios). Si la tabla manda `?per_page=N` con un valor permitido, se ajusta
 * ese valor solo para esta petición: así el selector de 20 / 50 / 100 funciona
 * en todas las pantallas sin tocar cada controlador.
 *
 * Solo valores fijos: nadie puede pedir 100.000 filas y tumbar el servidor.
 */
class ApplyPerPage
{
    public const ALLOWED = [10, 20, 25, 50, 100];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && $request->has('per_page')) {
            $n = (int) $request->query('per_page');
            if (in_array($n, self::ALLOWED, true)) {
                config(['tenant.items_per_page' => $n]);
            }
        }

        return $next($request);
    }
}
