<?php

namespace App\Services\Tenant\WhatsApp\Drivers;

use App\Services\Tenant\WhatsApp\Contracts\WhatsAppDriverInterface;

/**
 * Base común para drivers de WhatsApp — maneja normalización de teléfonos
 * y tracking del último error.
 */
abstract class AbstractDriver implements WhatsAppDriverInterface
{
    protected ?string $lastError = null;

    public function lastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Normaliza número al formato internacional esperado por la API.
     * Reglas:
     *   - Solo dígitos
     *   - Si tiene 9 dígitos y empieza con 9 → añade prefijo Perú '51'
     *   - Si tiene 8 dígitos → probablemente Perú fijo, añade '51' (heurística débil)
     *   - Otros casos pasan tal cual
     *
     * @return string|null  null si no se pudo normalizar (vacío).
     */
    protected function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (empty($phone)) return null;

        // Perú móvil: 9XXXXXXXX (9 dígitos empezando en 9)
        if (strlen($phone) === 9 && str_starts_with($phone, '9')) {
            $phone = '51' . $phone;
        }

        return $phone;
    }

    /**
     * Renderiza una plantilla localmente (fallback para drivers que no
     * soportan plantillas nativas). Reemplaza {{1}}, {{2}}, etc.
     */
    protected function renderTemplate(string $templateBody, array $parameters): string
    {
        $rendered = $templateBody;
        foreach ($parameters as $i => $value) {
            $rendered = str_replace('{{' . ($i + 1) . '}}', (string) $value, $rendered);
        }
        return $rendered;
    }
}
