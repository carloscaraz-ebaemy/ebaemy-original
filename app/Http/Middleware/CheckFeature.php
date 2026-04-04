<?php

namespace App\Http\Middleware;

use App\Services\FeatureGate;
use Closure;
use Illuminate\Http\Request;

/**
 * Middleware que verifica si el tenant activo tiene un feature habilitado.
 *
 * Uso en rutas:
 *   Route::middleware('feature:ecommerce')->group(...)
 *   Route::middleware('feature:smart_stock')->group(...)
 *
 * Si el tenant no tiene el feature, retorna 403 (API) o redirige al dashboard (web).
 */
class CheckFeature
{
    public function __construct(private readonly FeatureGate $gate) {}

    public function handle(Request $request, Closure $next, string $feature)
    {
        if ($this->gate->has($feature)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => "Tu plan no incluye esta funcionalidad. Contacta a soporte para actualizar tu plan.",
                'feature' => $feature,
            ], 403);
        }

        return redirect('/dashboard')
            ->with('flash_message', 'Tu plan actual no incluye esta funcionalidad. Contacta a soporte para actualizar tu plan.');
    }
}
