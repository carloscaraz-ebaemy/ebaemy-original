<?php

namespace App\Services\Tenant\WhatsApp\Drivers;

use App\Models\Tenant\Configuration;
use App\Services\Tenant\WhatsApp\Evolution\EvolutionClient;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp de la propia tienda conectado por QR (Evolution API, como el Pro 9).
 *
 * Solo se considera configurado con el número VINCULADO (estado «open»): una
 * instancia creada pero sin escanear no envía nada y no debe tapar a otro
 * driver que sí funcione.
 *
 * ⚠️  Es WhatsApp-Web, no la API oficial: volumen bajo y mensajes que el
 *    cliente espera (confirmaciones, comprobantes), no campañas masivas.
 */
class EvolutionDriver extends AbstractDriver
{
    protected ?string $instance;
    protected bool $connected;

    public function __construct(?Configuration $config = null)
    {
        $config = $config ?? Configuration::first();
        $this->instance = $config->qr_api_instance ?? null;
        $this->connected = ($config->qr_api_connection_state ?? null) === 'open';
    }

    public function name(): string
    {
        return 'evolution';
    }

    public function isConfigured(): bool
    {
        return $this->connected && !empty($this->instance) && EvolutionClient::available();
    }

    public function send(string $phone, string $message): bool
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'El WhatsApp de la tienda no está conectado';
            return false;
        }

        $phone = $this->normalizePhone($phone);
        if (!$phone) {
            $this->lastError = 'Número de teléfono inválido';
            return false;
        }

        try {
            $r = (new EvolutionClient())->sendText($this->instance, $phone, $message);
        } catch (\Throwable $e) {
            $this->lastError = 'Exception: ' . $e->getMessage();
            Log::warning('WhatsApp [evolution] send failed', ['phone' => $phone, 'error' => $e->getMessage()]);
            return false;
        }

        if (EvolutionClient::failed($r)) {
            $this->lastError = 'WhatsApp rechazó el mensaje';
            Log::warning('WhatsApp [evolution] rechazado', ['phone' => $phone, 'respuesta' => mb_substr(json_encode($r), 0, 300)]);
            return false;
        }

        return true;
    }

    public function sendTemplate(string $phone, string $templateName, array $parameters = [], string $languageCode = 'es'): bool
    {
        // Sin plantillas de Meta: se envía como texto, igual que qr_api.
        return $this->send($phone, $this->renderTemplate("[{$templateName}]", $parameters));
    }
}
