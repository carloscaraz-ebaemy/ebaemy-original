<?php

namespace App\Services\System;

/**
 * Reglas de los datos del comprador en el checkout central. Clase pura (sin BD)
 * para que el servidor valide lo mismo que el navegador y se pueda probar.
 *
 * Tipos de documento: DNI (8 dígitos), RUC (11), CE (carné de extranjería,
 * alfanumérico de 9 a 12) y Pasaporte (alfanumérico). El número se guarda sin
 * espacios ni guiones; en CE y pasaporte se conservan las LETRAS, que antes se
 * borraban con `\D+` y dejaban un pasaporte irreconocible.
 */
class MarketplaceCheckoutRules
{
    public const DOC_TYPES = ['DNI', 'RUC', 'CE', 'Pasaporte'];

    /** Tipo tal como lo guarda el checkout ('DNI', 'RUC', 'CE', 'Pasaporte') o null. */
    public static function docType(?string $type): ?string
    {
        $t = mb_strtolower(trim((string) $type));
        foreach (self::DOC_TYPES as $canon) {
            if (mb_strtolower($canon) === $t) {
                return $canon;
            }
        }

        return null;
    }

    /** Valor que entiende `shipping_requests.document_type` (dni|ruc|ce|pasaporte). */
    public static function shippingDocType(?string $type): ?string
    {
        $canon = self::docType($type);

        return $canon ? mb_strtolower($canon) : null;
    }

    /** Número limpio: dígitos para DNI/RUC; letras y números en mayúscula para CE/Pasaporte. */
    public static function normalizeDocNumber(?string $type, ?string $number): string
    {
        $canon = self::docType($type);
        $raw = trim((string) $number);
        if ($canon === 'CE' || $canon === 'Pasaporte') {
            return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw));
        }

        return preg_replace('/\D+/', '', $raw);
    }

    /**
     * Mensaje de error del documento, o null si está bien. El documento es
     * opcional salvo con RUC (sin número no hay factura que emitir).
     */
    public static function docError(?string $type, ?string $number): ?string
    {
        $canon = self::docType($type);
        $n = self::normalizeDocNumber($type, $number);

        if ($canon === null) {
            return $n === '' ? null : 'Elige el tipo de documento.';
        }
        if ($n === '') {
            return $canon === 'RUC' ? 'Ingresa el RUC de 11 dígitos.' : null;
        }

        return match ($canon) {
            'DNI'       => preg_match('/^\d{8}$/', $n) ? null : 'El DNI debe tener 8 dígitos.',
            'RUC'       => preg_match('/^\d{11}$/', $n) ? null : 'El RUC debe tener 11 dígitos.',
            'CE'        => preg_match('/^[A-Z0-9]{9,12}$/', $n) ? null : 'El carné de extranjería debe tener entre 9 y 12 letras o números.',
            'Pasaporte' => preg_match('/^[A-Z0-9]{6,12}$/', $n) ? null : 'El pasaporte debe tener entre 6 y 12 letras o números.',
        };
    }

    /** Celular peruano a 9 dígitos (quita el 51 inicial), o null si no es un celular válido. */
    public static function normalizePhone(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($d) === 11 && str_starts_with($d, '51')) {
            $d = substr($d, 2);
        }

        return preg_match('/^9\d{8}$/', $d) ? $d : null;
    }
}
