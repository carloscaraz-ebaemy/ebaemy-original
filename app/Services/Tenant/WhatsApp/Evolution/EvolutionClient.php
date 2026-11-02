<?php

namespace App\Services\Tenant\WhatsApp\Evolution;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente de Evolution API: conectar un WhatsApp por QR y enviar mensajes.
 *
 * Portado del Pro 9 (modules/WhatsAppBot/Services/Evolution/EvolutionClient),
 * que habla con el proxy de Buho. Aquí además sabe hablar con un Evolution
 * propio (plan B) sin que el resto del sistema note la diferencia: mismos
 * métodos, mismas respuestas normalizadas. Ver config/whatsapp_evolution.php.
 */
class EvolutionClient
{
    private string $mode;
    private string $baseUrl;
    private array $headers;
    private int $timeout;

    public function __construct()
    {
        $cfg = config('whatsapp_evolution');
        $this->mode = ($cfg['mode'] ?? 'buho') === 'evolution' ? 'evolution' : 'buho';
        $this->timeout = (int) ($cfg['timeout'] ?? 30);

        if ($this->mode === 'buho') {
            $c = $cfg['buho'] ?? [];
            if (empty($c['url']) || empty($c['client_id']) || empty($c['client_secret'])) {
                throw new RuntimeException('La conexión de WhatsApp no está configurada en el servidor.');
            }
            $this->baseUrl = rtrim($c['url'], '/');
            $this->headers = ['Authorization' => 'Basic ' . base64_encode($c['client_id'] . ':' . $c['client_secret'])];
        } else {
            $c = $cfg['evolution'] ?? [];
            if (empty($c['url']) || empty($c['api_key'])) {
                throw new RuntimeException('La conexión de WhatsApp no está configurada en el servidor.');
            }
            $this->baseUrl = rtrim($c['url'], '/');
            $this->headers = ['apikey' => $c['api_key']];
        }
    }

    /** ¿Hay credenciales en el servidor? Sin ellas la conexión no se ofrece. */
    public static function available(): bool
    {
        try {
            new self();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function http(?int $timeout = null): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders($this->headers)
            ->acceptJson()
            ->timeout($timeout ?? $this->timeout);
    }

    public function createInstance(string $instance): array
    {
        $r = $this->mode === 'buho'
            ? $this->http()->post('/api/bot/instances', ['instance_name' => $instance])
            : $this->http()->post('/instance/create', [
                'instanceName' => $instance,
                'qrcode'       => true,
                'integration'  => 'WHATSAPP-BAILEYS',
            ]);

        if ($r->successful()) {
            return $r->json() ?: [];
        }
        $body = strtolower($r->body());
        if (str_contains($body, 'already exists') || str_contains($body, 'already in use')) {
            return ['ok' => true, 'already_exists' => true];
        }

        throw new RuntimeException("No se pudo crear la conexión (HTTP {$r->status()}).");
    }

    public function qr(string $instance): array
    {
        return $this->http()->get($this->mode === 'buho'
            ? "/api/bot/instances/{$instance}/qr"
            : "/instance/connect/{$instance}")->json() ?: [];
    }

    public function state(string $instance): array
    {
        return $this->http()->get($this->mode === 'buho'
            ? "/api/bot/instances/{$instance}/state"
            : "/instance/connectionState/{$instance}")->json() ?: [];
    }

    /** Número y nombre del WhatsApp conectado: ['phone' => ?, 'name' => ?]. */
    public function owner(string $instance): array
    {
        $info = $this->mode === 'buho'
            ? ($this->http()->get("/api/bot/instances/{$instance}/info")->json() ?: [])
            : ($this->http()->get('/instance/fetchInstances', ['instanceName' => $instance])->json() ?: []);
        $first = is_array($info) && isset($info[0]) ? $info[0] : $info;
        $owner = data_get($first, 'instance.owner') ?: data_get($first, 'owner') ?: data_get($first, 'ownerJid');
        $name = data_get($first, 'instance.profileName') ?: data_get($first, 'profileName');

        return [
            'phone' => $owner ? preg_replace('/\D/', '', explode('@', (string) $owner)[0]) : null,
            'name'  => $name ?: null,
        ];
    }

    public function sendText(string $instance, string $number, string $text): array
    {
        $number = preg_replace('/\D/', '', $number);
        $r = $this->mode === 'buho'
            ? $this->http()->post("/api/bot/instances/{$instance}/messages/text", [
                'number' => $number, 'text' => $text, 'delay' => 0, 'linkPreview' => true,
            ])
            : $this->http()->post("/message/sendText/{$instance}", [
                'number' => $number, 'text' => $text, 'linkPreview' => true,
            ]);

        return $r->json() ?: [];
    }

    /** Documento (PDF en base64): mediatype, mimetype, media, fileName, caption. */
    public function sendMedia(string $instance, string $number, array $media): array
    {
        $payload = array_merge(['number' => preg_replace('/\D/', '', $number)], $media);

        return $this->http(60)->post($this->mode === 'buho'
            ? "/api/bot/instances/{$instance}/messages/media"
            : "/message/sendMedia/{$instance}", $payload)->json() ?: [];
    }

    public function deleteInstance(string $instance): void
    {
        try {
            if ($this->mode === 'buho') {
                $this->http()->delete("/api/bot/instances/{$instance}");
            } else {
                $this->http()->delete("/instance/logout/{$instance}");
                $this->http()->delete("/instance/delete/{$instance}");
            }
        } catch (\Throwable $e) {
            // Se limpia igual del lado de la tienda.
        }
    }

    public static function extractQr(array $r): ?string
    {
        foreach ([
            $r['qr'] ?? null,
            $r['qrcode']['base64'] ?? null,
            $r['base64'] ?? null,
            is_string($r['qrcode'] ?? null) ? $r['qrcode'] : null,
            $r['qr_code'] ?? null,
            $r['image'] ?? null,
        ] as $qr) {
            if (!empty($qr) && is_string($qr)) {
                return str_starts_with($qr, 'data:image/') ? $qr : 'data:image/png;base64,' . $qr;
            }
        }

        return null;
    }

    public static function isConnected(array $r): bool
    {
        return ($r['state'] ?? null) === 'open'
            || ($r['status'] ?? null) === 'connected'
            || data_get($r, 'instance.state') === 'open'
            || data_get($r, 'instance.status') === 'connected'
            || ($r['connected'] ?? false) === true;
    }

    /** Evolution no lanza excepción al rechazar un mensaje: hay que mirar la respuesta. */
    public static function failed(array $r): bool
    {
        return isset($r['error'])
            || (array_key_exists('success', $r) && !$r['success'])
            || (isset($r['status']) && is_numeric($r['status']) && (int) $r['status'] >= 400);
    }
}
