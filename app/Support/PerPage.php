<?php

namespace App\Support;

use App\Http\Middleware\ApplyPerPage;

/**
 * «Filas por página» para los listados que NO paginan con
 * `config('tenant.items_per_page')` (que ya ajusta el middleware ApplyPerPage):
 * los que tienen su propio número (50 en el POS, 25 en Hotel, 5 en las mini
 * tablas del dashboard…) o su propio config.
 *
 * Devuelve el `per_page` de la petición si es uno de los permitidos
 * (ApplyPerPage::ALLOWED) y, si no, el tamaño de siempre de esa pantalla.
 * Lee de la query string, así que sirve también en POST con `?per_page=`.
 */
class PerPage
{
    public static function resolve(int $default): int
    {
        $n = (int) request()->query('per_page');

        return in_array($n, ApplyPerPage::ALLOWED, true) ? $n : $default;
    }
}
