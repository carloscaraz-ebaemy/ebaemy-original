<?php

namespace App\Services\Tenant;

use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Facebook Conversions API (CAPI) — server-side event tracking.
 *
 * @see https://developers.facebook.com/docs/marketing-api/conversions-api
 */
class FacebookConversionsApiService
{
    private const API_VERSION = 'v19.0';
    private const BASE_URL    = 'https://graph.facebook.com';

    private ?string $pixelId;
    private ?string $accessToken;

    public function __construct(?string $pixelId = null, ?string $accessToken = null)
    {
        $this->pixelId     = $pixelId;
        $this->accessToken = $accessToken;
    }

    /**
     * Build instance from tenant config.
     */
    public static function fromConfig(): ?self
    {
        $config = ConfigurationEcommerce::firstCached();

        if (!$config || empty($config->facebook_pixel_id) || empty($config->facebook_capi_token)) {
            return null;
        }

        return new self($config->facebook_pixel_id, $config->facebook_capi_token);
    }

    /**
     * Send a Purchase event to the Conversions API.
     */
    public function sendPurchaseEvent(array $data): bool
    {
        return $this->sendEvent('Purchase', $data);
    }

    /**
     * Send any standard event to the Conversions API.
     *
     * @param string $eventName  Standard event name (Purchase, AddToCart, ViewContent, etc.)
     * @param array  $data       Event data with keys:
     *   - event_id:     string  (for deduplication with browser pixel)
     *   - value:        float
     *   - currency:     string
     *   - content_ids:  array
     *   - content_type: string  (product|product_group)
     *   - email:        string  (optional, hashed automatically)
     *   - phone:        string  (optional, hashed automatically)
     *   - client_ip:    string  (optional)
     *   - user_agent:   string  (optional)
     *   - fbc:          string  (optional, _fbc cookie)
     *   - fbp:          string  (optional, _fbp cookie)
     */
    public function sendEvent(string $eventName, array $data): bool
    {
        if (empty($this->pixelId) || empty($this->accessToken)) {
            return false;
        }

        $eventId = $data['event_id'] ?? Str::uuid()->toString();

        $userData = [];

        if (!empty($data['email'])) {
            $userData['em'] = [hash('sha256', strtolower(trim($data['email'])))];
        }
        if (!empty($data['phone'])) {
            $phone = preg_replace('/[^0-9]/', '', $data['phone']);
            $userData['ph'] = [hash('sha256', $phone)];
        }
        if (!empty($data['client_ip'])) {
            $userData['client_ip_address'] = $data['client_ip'];
        }
        if (!empty($data['user_agent'])) {
            $userData['client_user_agent'] = $data['user_agent'];
        }
        if (!empty($data['fbc'])) {
            $userData['fbc'] = $data['fbc'];
        }
        if (!empty($data['fbp'])) {
            $userData['fbp'] = $data['fbp'];
        }

        $customData = [];

        if (isset($data['value'])) {
            $customData['value'] = (float) $data['value'];
        }
        if (!empty($data['currency'])) {
            $customData['currency'] = strtoupper($data['currency']);
        }
        if (!empty($data['content_ids'])) {
            $customData['content_ids'] = $data['content_ids'];
        }
        if (!empty($data['content_type'])) {
            $customData['content_type'] = $data['content_type'];
        }
        if (!empty($data['order_id'])) {
            $customData['order_id'] = $data['order_id'];
        }
        if (!empty($data['contents'])) {
            $customData['contents'] = $data['contents'];
        }
        if (!empty($data['num_items'])) {
            $customData['num_items'] = (int) $data['num_items'];
        }

        $event = [
            'event_name'      => $eventName,
            'event_time'      => $data['event_time'] ?? time(),
            'event_id'        => $eventId,
            'event_source_url' => $data['source_url'] ?? null,
            'action_source'   => 'website',
            'user_data'       => $userData,
            'custom_data'     => $customData,
        ];

        // Remove null values
        $event = array_filter($event, fn ($v) => $v !== null);

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $this->pixelId . '/events';

        try {
            $response = Http::timeout(10)
                ->post($url, [
                    'data'         => [$event],
                    'access_token' => $this->accessToken,
                ]);

            if (!$response->successful()) {
                Log::channel('daily')->warning('Facebook CAPI error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'event'  => $eventName,
                ]);
                return false;
            }

            Log::channel('daily')->info('Facebook CAPI event sent', [
                'event'    => $eventName,
                'event_id' => $eventId,
                'events_received' => $response->json('events_received'),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('daily')->error('Facebook CAPI exception', [
                'event'   => $eventName,
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Test the connection by sending a test event.
     */
    public function testConnection(): array
    {
        if (empty($this->pixelId) || empty($this->accessToken)) {
            return ['success' => false, 'message' => 'Pixel ID o token CAPI no configurado'];
        }

        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . $this->pixelId . '/events';

        try {
            $response = Http::timeout(10)
                ->post($url, [
                    'data' => [[
                        'event_name'    => 'PageView',
                        'event_time'    => time(),
                        'event_id'      => Str::uuid()->toString(),
                        'action_source' => 'website',
                        'user_data'     => [
                            'client_ip_address' => '0.0.0.0',
                            'client_user_agent' => 'CAPI Test',
                        ],
                    ]],
                    'access_token' => $this->accessToken,
                    'test_event_code' => 'TEST' . random_int(10000, 99999),
                ]);

            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? 'Conexion exitosa — evento de prueba enviado'
                    : 'Error: ' . $response->body(),
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error de conexion: ' . $e->getMessage()];
        }
    }
}
