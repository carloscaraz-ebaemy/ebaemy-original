<?php

namespace App\Services\System;

/**
 * TOTP (Time-based One-Time Password) — RFC 6238 / RFC 4226
 *
 * Implementación pura en PHP sin dependencias externas.
 * Compatible con Google Authenticator, Authy y cualquier app TOTP estándar.
 *
 * Uso:
 *   $secret = TotpService::generateSecret();         // generar y guardar en BD
 *   $url    = TotpService::getQrUrl($secret, $email); // para mostrar QR
 *   $valid  = TotpService::verify($secret, $code);    // validar código del usuario
 */
class TotpService
{
    // Tabla Base32 (RFC 4648)
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // Ventana de tolerancia: ±1 período de 30 s (cubre desfase de reloj)
    private const WINDOW = 1;

    // ─── Generación de secreto ────────────────────────────────────────────────

    /**
     * Genera un secreto aleatorio de 160 bits codificado en Base32 (32 chars).
     */
    public static function generateSecret(): string
    {
        $bytes = random_bytes(20); // 160 bits
        return static::base32Encode($bytes);
    }

    // ─── QR Code URL ─────────────────────────────────────────────────────────

    /**
     * Retorna la URL otpauth:// para generar el QR que escanea la app TOTP.
     */
    public static function getOtpauthUrl(string $secret, string $email, string $issuer = null): string
    {
        $issuer = $issuer ?? config('app.name', 'ERP');
        return 'otpauth://totp/'
            . rawurlencode($issuer . ':' . $email)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /**
     * URL de Google Charts para renderizar el QR en el navegador.
     * Requiere conexión a internet; sólo para setup inicial.
     */
    public static function getQrImageUrl(string $secret, string $email, string $issuer = null): string
    {
        $otpauth = static::getOtpauthUrl($secret, $email, $issuer);
        return 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl='
            . rawurlencode($otpauth);
    }

    // ─── Verificación ─────────────────────────────────────────────────────────

    /**
     * Verifica un código de 6 dígitos contra el secreto.
     * Acepta el período actual ± WINDOW períodos para tolerar desfase de reloj.
     *
     * @param  string $secret  Secreto Base32 guardado en BD (sin encriptar, ya descifrado)
     * @param  string $code    Código de 6 dígitos introducido por el usuario
     */
    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code); // quitar espacios
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = (int) floor(time() / 30);

        for ($offset = -static::WINDOW; $offset <= static::WINDOW; $offset++) {
            if (static::hotp($secret, $counter + $offset) === $code) {
                return true;
            }
        }

        return false;
    }

    // ─── HOTP (RFC 4226) ──────────────────────────────────────────────────────

    private static function hotp(string $secret, int $counter): string
    {
        $key     = static::base32Decode($secret);
        $message = pack('N*', 0) . pack('N*', $counter); // contador como uint64 big-endian

        $hash    = hash_hmac('sha1', $message, $key, true);
        $offset  = ord($hash[19]) & 0x0F;

        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
            ((ord($hash[$offset + 3]) & 0xFF))
        ) % 1_000_000;

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    // ─── Base32 ───────────────────────────────────────────────────────────────

    public static function base32Encode(string $bytes): string
    {
        $chars  = static::BASE32_CHARS;
        $result = '';
        $buffer = 0;
        $bits   = 0;

        foreach (str_split($bytes) as $byte) {
            $buffer = ($buffer << 8) | ord($byte);
            $bits  += 8;
            while ($bits >= 5) {
                $bits  -= 5;
                $result .= $chars[($buffer >> $bits) & 0x1F];
            }
        }

        if ($bits > 0) {
            $result .= $chars[($buffer << (5 - $bits)) & 0x1F];
        }

        return $result;
    }

    private static function base32Decode(string $input): string
    {
        $input  = strtoupper(preg_replace('/[^A-Z2-7]/', '', $input));
        $chars  = static::BASE32_CHARS;
        $lookup = array_flip(str_split($chars));
        $result = '';
        $buffer = 0;
        $bits   = 0;

        foreach (str_split($input) as $char) {
            if (!isset($lookup[$char])) continue;
            $buffer = ($buffer << 5) | $lookup[$char];
            $bits  += 5;
            if ($bits >= 8) {
                $bits  -= 8;
                $result .= chr(($buffer >> $bits) & 0xFF);
            }
        }

        return $result;
    }
}
