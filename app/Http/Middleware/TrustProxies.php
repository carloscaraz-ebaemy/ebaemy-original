<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies as Middleware;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * En producción corremos detrás de OpenResty que termina TLS y reenvía
     * a PHP-FPM vía HTTP con el header `X-Forwarded-Proto: https`. Si dejamos
     * $proxies en null, Laravel ignora ese header y genera URLs con http://,
     * rompiendo paginación AJAX (CSP connect-src violation + pushState
     * SecurityError por cross-origin).
     *
     * '*' confía en cualquier proxy en la cadena — seguro porque PHP-FPM
     * solo escucha en socket unix local (no expuesto a internet).
     *
     * @var array|string|null
     */
    protected $proxies = '*';

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
