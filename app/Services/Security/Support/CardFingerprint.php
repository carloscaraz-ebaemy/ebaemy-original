<?php

namespace App\Services\Security\Support;

/**
 * Huella de una tarjeta para detectar fraude, sin guardar la tarjeta.
 *
 * El numero completo NUNCA se almacena ni se pasa por aqui. Solo entran el BIN
 * (los seis primeros digitos, que identifican al banco emisor, no al titular) y
 * los cuatro ultimos. De ahi sale un HMAC-SHA256 con la APP_KEY como clave:
 * dos pedidos pagados con la misma tarjeta dan la misma huella, pero de la
 * huella no se puede reconstruir nada, ni siquiera por fuerza bruta sin la
 * APP_KEY.
 */
final class CardFingerprint
{
    /**
     * Saca last4 y huella de una respuesta de cargo de Culqi.
     *
     * @return array{last4:?string,fingerprint:?string}
     */
    public static function fromCulqiCharge($charge): array
    {
        $source = self::arrayFrom($charge->source ?? null);

        if (!$source) {
            return ['last4' => null, 'fingerprint' => null];
        }

        // Culqi devuelve el numero enmascarado: "411111******1111".
        $masked = (string) ($source['card_number'] ?? '');
        $last4  = preg_match('/(\d{4})$/', $masked, $m) ? $m[1] : null;

        $iin = self::arrayFrom($source['iin'] ?? null);
        $bin = isset($iin['bin']) ? preg_replace('/\D/', '', (string) $iin['bin']) : null;

        if (!$bin && preg_match('/^(\d{6})/', $masked, $m)) {
            $bin = $m[1];
        }

        return [
            'last4'       => $last4,
            'fingerprint' => self::make($bin, $last4),
        ];
    }

    /** Huella estable a partir del BIN y los ultimos cuatro digitos. */
    public static function make(?string $bin, ?string $last4): ?string
    {
        if (!$last4) {
            return null;
        }

        return hash_hmac('sha256', ($bin ?? '') . '|' . $last4, (string) config('app.key'));
    }

    private static function arrayFrom($value): array
    {
        if (is_array($value))  return $value;
        if (is_object($value)) return json_decode(json_encode($value), true) ?: [];

        return [];
    }
}
