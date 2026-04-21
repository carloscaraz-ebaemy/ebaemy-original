<?php

namespace App\Services\Tenant\WhatsApp\Drivers;

use Illuminate\Support\Facades\Log;

/**
 * Driver no-op: cuando no hay ningún proveedor configurado.
 * Todos los `send()` retornan false silenciosamente (solo logs en debug).
 *
 * Útil para tenants recién creados que aún no han configurado WhatsApp —
 * el sistema no falla, simplemente no envía.
 */
class NoneDriver extends AbstractDriver
{
    public function name(): string
    {
        return 'none';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function send(string $phone, string $message): bool
    {
        Log::debug('WhatsApp [none] skipped — no driver configured', [
            'phone'   => $phone,
            'message' => substr($message, 0, 60),
        ]);
        $this->lastError = 'Sin driver configurado';
        return false;
    }

    public function sendTemplate(string $phone, string $templateName, array $parameters = [], string $languageCode = 'es'): bool
    {
        return $this->send($phone, "[{$templateName}]");
    }
}
