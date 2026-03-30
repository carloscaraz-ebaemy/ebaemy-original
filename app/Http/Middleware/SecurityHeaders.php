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

        // Activa el filtro XSS del navegador (legacy, reforzado por CSP)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Controla la información enviada en el header Referer
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Forza HTTPS en navegadores que ya visitaron el sitio (6 meses)
        $response->headers->set('Strict-Transport-Security', 'max-age=15552000; includeSubDomains');

        // Permissions Policy: desactiva funcionalidades de navegador no necesarias
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Content-Security-Policy — solo para rutas de ecommerce público
        // El panel admin usa Vite + CDNs variados que CSP estricto bloquea
        $path = $request->path();
        $isEcommerce = str_starts_with($path, 'ecommerce');

        if ($isEcommerce) {
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net https://checkout.culqi.com https://www.googletagmanager.com https://connect.facebook.net https://cdnjs.cloudflare.com",
                "style-src 'self' 'unsafe-inline' https://unpkg.com https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
                "img-src 'self' data: https: blob:",
                "font-src 'self' data: https://fonts.gstatic.com https://unpkg.com https://cdn.jsdelivr.net",
                "connect-src 'self' https://api.culqi.com https://graph.facebook.com https://www.google-analytics.com",
                "frame-ancestors 'self'",
                "object-src 'none'",
                "base-uri 'self'",
            ]);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
