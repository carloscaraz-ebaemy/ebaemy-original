<?php

namespace App\Services\Tenant\WhatsApp\Drivers;

use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Driver para Meta Cloud API (oficial, graph.facebook.com).
 *
 * Configuración (tenant):
 *   - configuration_ecommerce.whatsapp_api_token       (Bearer token permanente)
 *   - configuration_ecommerce.whatsapp_phone_number_id (o whatsapp_phone_id — legacy)
 *
 * Límites:
 *   - 1000 conversaciones gratis al mes.
 *   - Después: ~$0.02-$0.05 USD por conversación.
 *   - Mensajes iniciados por negocio requieren plantilla aprobada.
 */
class MetaCloudDriver extends AbstractDriver
{
    protected ?string $token;
    protected ?string $phoneNumberId;
    protected string $apiVersion;
    protected string $baseUrl = 'https://graph.facebook.com';

    public function __construct(?ConfigurationEcommerce $config = null, string $apiVersion = 'v18.0')
    {
        $config = $config ?? ConfigurationEcommerce::first();
        $this->token = $config->whatsapp_api_token ?? null;
        // Soporta ambos nombres de columna (legacy + nuevo)
        $this->phoneNumberId = $config->whatsapp_phone_number_id
            ?? $config->whatsapp_phone_id
            ?? null;
        $this->apiVersion = $apiVersion;
    }

    public function name(): string
    {
        return 'meta_cloud';
    }

    public function isConfigured(): bool
    {
        return !empty($this->token) && !empty($this->phoneNumberId);
    }

    public function send(string $phone, string $message): bool
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'Meta Cloud no configurado (falta token o phone_number_id)';
            return false;
        }

        $phone = $this->normalizePhone($phone);
        if (!$phone) {
            $this->lastError = 'Número de teléfono inválido';
            return false;
        }

        return $this->postToMeta($phone, [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'text',
            'text'              => ['body' => $message, 'preview_url' => false],
        ]);
    }

    public function sendTemplate(string $phone, string $templateName, array $parameters = [], string $languageCode = 'es'): bool
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'Meta Cloud no configurado';
            return false;
        }

        $phone = $this->normalizePhone($phone);
        if (!$phone) return false;

        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn($v) => [
                    'type' => 'text',
                    'text' => (string) $v,
                ], $parameters),
            ];
        }

        return $this->postToMeta($phone, [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'       => $templateName,
                'language'   => ['code' => $languageCode],
                'components' => $components,
            ],
        ]);
    }

    protected function postToMeta(string $phone, array $payload): bool
    {
        try {
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->post("{$this->baseUrl}/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload);

            if ($response->successful()) {
                Log::channel('payments')->info('WhatsApp [meta_cloud] sent', [
                    'phone' => $phone,
                    'type'  => $payload['type'] ?? 'text',
                    'id'    => $response->json('messages.0.id'),
                ]);
                return true;
            }

            $errorMsg = $response->json('error.message') ?? substr($response->body(), 0, 200);
            $this->lastError = "Meta Cloud error {$response->status()}: {$errorMsg}";
            Log::warning('WhatsApp [meta_cloud] API error', [
                'phone'  => $phone,
                'status' => $response->status(),
                'error'  => $errorMsg,
            ]);
            return false;
        } catch (\Throwable $e) {
            $this->lastError = 'Exception: ' . $e->getMessage();
            Log::warning('WhatsApp [meta_cloud] send failed', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
