<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Evita que la app sea embebida en iframes de otros dominios
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Evita que el navegador detecte automáticamente el tipo de contenido
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Activa el filtro XSS del navegador
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Controla la información enviada en el header Referer
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Forza HTTPS en navegadores que ya visitaron el sitio (6 meses)
        $response->headers->set('Strict-Transport-Security', 'max-age=15552000; includeSubDomains');

        // Permissions Policy: desactiva funcionalidades de navegador no necesarias
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        return $response;
    }
}
