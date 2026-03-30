<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * AuthOrSigned — Permite acceso si el usuario está autenticado O la URL está firmada.
 *
 * Uso en rutas:
 *   Route::get('print/{doc}', ...)->middleware('auth.or.signed');
 *
 * Generar URL firmada:
 *   URL::signedRoute('print.document', ['doc' => $id], now()->addHour());
 *
 * Esto protege las rutas de print/download sin romper:
 *  - Links compartidos por email (usan URL firmada con expiración)
 *  - Usuarios logueados que imprimen desde el panel
 */
class AuthOrSigned
{
    public function handle(Request $request, Closure $next)
    {
        // Si está autenticado → pasar
        if (auth()->check()) {
            return $next($request);
        }

        // Si la URL tiene firma válida → pasar
        if ($request->hasValidSignature()) {
            return $next($request);
        }

        // Sin auth ni firma → rechazar
        abort(403, 'Acceso no autorizado. Inicie sesión o use un enlace válido.');
    }
}
