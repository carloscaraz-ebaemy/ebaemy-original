<?php

namespace App\Services\Tenant\WhatsApp\Drivers;

use App\Models\Tenant\Configuration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver para gateway tipo QR API (WhatsApp-Web automatizado).
 *
 * Configuración (tenant):
 *   - configurations.qr_api_enable  (bool, activar/desactivar)
 *   - configurations.qr_api_url     (URL base del gateway, ej: https://wa.midominio.com)
 *   - configurations.qr_api_apiKey  (Bearer token del gateway)
 *
 * Endpoint esperado: {url}/api/message/send-text
 * Payload: { "number": "51XXXXXXXXX", "message": "texto" }
 *
 * ⚠️  WhatsApp-Web no es oficial. Uso bajo tu responsabilidad.
 *    Meta puede banear el número si detecta automatización masiva.
 *    Recomendado para volumen bajo o como fallback.
 */
class QrApiDriver extends AbstractDriver
{
    protected ?string $url;
    protected ?string $apiKey;
    protected bool $enabled;

    public function __construct(?Configuration $config = null)
    {
        $config = $config ?? Configuration::first();
        $this->enabled = (bool) ($config->qr_api_enable ?? false);
        $this->url = $config->qr_api_url ? rtrim($config->qr_api_url, '/') : null;
        $this->apiKey = $config->qr_api_apiKey ?? null;
    }

    public function name(): string
    {
        return 'qr_api';
    }

    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->url) && !empty($this->apiKey);
    }

    public function send(string $phone, string $message): bool
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'QR API no configurado o deshabilitado';
            return false;
        }

        $phone = $this->normalizePhone($phone);
        if (!$phone) {
            $this->lastError = 'Número de teléfono inválido';
            return false;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->withOptions(['verify' => false]) // gateway suele tener cert autofirmado
                ->timeout(15)
                ->post($this->url . '/api/message/send-text', [
                    'number'  => $phone,
                    'message' => $message,
                ]);

            if ($response->successful()) {
                Log::channel('payments')->info('WhatsApp [qr_api] sent', ['phone' => $phone]);
                return true;
            }

            $this->lastError = "QR API error {$response->status()}";
            Log::warning('WhatsApp [qr_api] API error', [
                'phone'  => $phone,
                'status' => $response->status(),
                'body'   => substr($response->body(), 0, 200),
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->lastError = 'Exception: ' . $e->getMessage();
            Log::warning('WhatsApp [qr_api] send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendTemplate(string $phone, string $templateName, array $parameters = [], string $languageCode = 'es'): bool
    {
        // QR API no soporta plantillas Meta — fallback: enviar el nombre de la plantilla como texto.
        // Para uso real, WhatsAppManager debería renderizar la plantilla a texto antes de llamar a send().
        return $this->send($phone, $this->renderTemplate("[{$templateName}]", $parameters));
    }
}
