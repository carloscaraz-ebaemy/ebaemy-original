<?php

namespace Modules\LevelAccess\Http\Middleware;

use Closure;
use App\Models\Tenant\Configuration;


class CheckEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse|null
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        // Este middleware valida verificación de email; no fuerza autenticación.
        if (!$user) {
            return $next($request);
        }

        if (Configuration::getDataToCheckGuestEmail()->applyCheckGuestEmail()) {
            if ($user->isNotVerifiedUserEmail()) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Email no verificado.',
                    ], 403);
                }

                return redirect()->route('tenant.not-verified-email.index');
            }
        }

        return $next($request);
    }

}
