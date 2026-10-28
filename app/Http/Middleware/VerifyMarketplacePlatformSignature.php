<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Autentica a un SISTEMA externo (hoy PRO 9), no a una tienda: es la llave con
 * la que sus tiendas se registran solas en el marketplace.
 *
 * Cabeceras:
 *   X-Platform-Key  clave del sistema (config services.marketplace_platforms.*.key)
 *   X-Timestamp     unix epoch en segundos (ventana de ±5 min)
 *   X-Signature     hex(HMAC-SHA256(secreto, "{timestamp}\n{cuerpo crudo}"))
 *
 * Deja el nombre de la plataforma y su config en $request->attributes
 * ('marketplace_platform', 'marketplace_platform_config').
 */
class VerifyMarketplacePlatformSignature
{
    private const WINDOW_SECONDS = 300;

    public function handle(Request $request, Closure $next)
    {
        $key       = (string) $request->header('X-Platform-Key');
        $timestamp = (string) $request->header('X-Timestamp');
        $signature = (string) $request->header('X-Signature');

        if ($key === '' || $signature === '' || !ctype_digit($timestamp)
            || abs(time() - (int) $timestamp) > self::WINDOW_SECONDS) {
            return response()->json(['message' => 'Petición no autenticada'], 401);
        }

        foreach ((array) config('services.marketplace_platforms', []) as $name => $cfg) {
            if (empty($cfg['key']) || empty($cfg['secret']) || !hash_equals((string) $cfg['key'], $key)) {
                continue;
            }
            $expected = hash_hmac('sha256', $timestamp . "\n" . $request->getContent(), (string) $cfg['secret']);
            if (!hash_equals($expected, $signature)) {
                break;
            }
            $request->attributes->set('marketplace_platform', $name);
            $request->attributes->set('marketplace_platform_config', $cfg);
            return $next($request);
        }

        return response()->json(['message' => 'Credenciales de plataforma inválidas'], 401);
    }
}
