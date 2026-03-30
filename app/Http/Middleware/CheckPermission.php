<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'No autenticado.');
        }

        // Super-admin bypasses all permission checks
        if (method_exists($user, 'hasRole') && $user->hasRole('super-admin')) {
            return $next($request);
        }

        // Legacy admin type also bypasses (backward compatibility during migration)
        if (($user->type ?? '') === 'admin') {
            return $next($request);
        }

        // Check RBAC permission
        if (method_exists($user, 'hasPermission') && $user->hasPermission($permission)) {
            return $next($request);
        }

        abort(403, 'No tiene permiso para esta acción.');
    }
}
