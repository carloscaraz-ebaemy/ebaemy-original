<?php

namespace App\Http\Middleware;

use App\Models\System\MarketplaceExternalSeller;
use Closure;
use Illuminate\Http\Request;

/**
 * Autentica a un vendedor externo (PRO 9) en la API del marketplace.
 *
 * Cabeceras:
 *   X-Seller-Key   clave pública del vendedor
 *   X-Timestamp    unix epoch en segundos (ventana de ±5 min contra reenvíos)
 *   X-Signature    hex(HMAC-SHA256(secreto, "{timestamp}\n{cuerpo crudo}"))
 *
 * Deja el vendedor en $request->attributes->get('external_seller').
 */
class VerifyExternalSellerSignature
{
    private const WINDOW_SECONDS = 300;

    public function handle(Request $request, Closure $next)
    {
        $key       = (string) $request->header('X-Seller-Key');
        $timestamp = (string) $request->header('X-Timestamp');
        $signature = (string) $request->header('X-Signature');

        if ($key === '' || $signature === '' || !ctype_digit($timestamp)) {
            return response()->json(['message' => 'Faltan cabeceras de autenticación'], 401);
        }
        if (abs(time() - (int) $timestamp) > self::WINDOW_SECONDS) {
            return response()->json(['message' => 'Petición expirada: revisa la hora del servidor'], 401);
        }

        $seller = MarketplaceExternalSeller::where('api_key', $key)->first();
        if (!$seller || !hash_equals($seller->sign($timestamp, $request->getContent()), $signature)) {
            return response()->json(['message' => 'Credenciales inválidas'], 401);
        }
        // Una tienda por aprobar ya puede enviar productos (quedan ocultos);
        // una rechazada o suspendida no.
        if (!$seller->canUseApi()) {
            return response()->json([
                'message'     => 'Tienda ' . mb_strtolower($seller->statusLabel()) . ' en el marketplace',
                'status'      => $seller->status,
                'admin_notes' => $seller->admin_notes,
            ], 403);
        }

        $seller->forceFill(['last_request_at' => now()])->saveQuietly();
        $request->attributes->set('external_seller', $seller);

        return $next($request);
    }
}
