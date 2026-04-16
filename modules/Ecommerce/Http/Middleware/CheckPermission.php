<?php

namespace Modules\Ecommerce\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Este middleware no reemplaza auth; solo valida acceso al módulo cuando hay usuario.
        if (!$user) {
            return $next($request);
        }

        $modules = $user->getModules();
        $access_modules = $modules->filter(function ($module, $key) {
            return $module->value === 'ecommerce';
        });

        if ($access_modules->count() === 0) {
            abort(404);
        }

        return $next($request);
    }
}
