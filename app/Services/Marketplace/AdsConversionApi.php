<?php

namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío server-side de conversiones a Meta (Conversions API) y TikTok
 * (Events API).
 *
 * ¿Por qué no basta el píxel del navegador? Porque se pierde una parte
 * grande de los eventos: iOS/ATT, bloqueadores, pestañas que se cierran antes
 * de que el script cargue. El `Purchase` es justo el evento que más importa y
 * el que más se pierde, porque ocurre al final. El navegador manda el evento
 * optimista; esta clase manda la verdad.
 *
 * El mismo evento llega dos veces a la plataforma, y eso es intencional: van
 * con el MISMO `event_id` y la plataforma deduplica. Sin ese id se cuenta
 * doble y el ROAS sale inflado.
 *
 * Nada de esto se llama desde una petición web: va por cola, en
 * `SendAdsConversion`. Dos APIs externas en el camino del checkout es
 * exactamente cómo se cuelga una compra.
 */
class AdsConversionApi
{
    private const META_VERSION   = 'v21.0';
    private const META_ENDPOINT  = 'https://graph.facebook.com/%s/%s/events';
    private const TIKTOK_ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    private const TIMEOUT = 10;

    /** Nombre canónico → nombre en cada plataforma. */
    private const EVENT_MAP = [
        'view_content'      => ['meta' => 'ViewContent',      'tiktok' => 'ViewContent'],
        'add_to_cart'       => ['meta' => 'AddToCart',        'tiktok' => 'AddToCart'],
        'initiate_checkout' => ['meta' => 'InitiateCheckout', 'tiktok' => 'InitiateCheckout'],
        'purchase'          => ['meta' => 'Purchase',         'tiktok' => 'CompletePayment'],
        'lead'              => ['meta' => 'Lead',             'tiktok' => 'SubmitForm'],
    ];

    /**
     * Manda el evento a las plataformas que estén configuradas.
     *
     * @param  string $event     Nombre canónico (clave de EVENT_MAP).
     * @param  array  $payload   ['event_id','value','currency','items','url']
     * @param  array  $userData  Salida de AdsTracking::userDataFromRequest()
     * @param  array  $customer  ['email','phone'] en claro — se hashean aquí
     * @return array  Resultado por plataforma, con el motivo si falló.
     */
    public function send(string $event, array $payload, array $userData = [], array $customer = []): array
    {
        if (!isset(self::EVENT_MAP[$event])) {
            return ['error' => "Evento desconocido: {$event}"];
        }

        $cfg     = AdsTracking::config();
        $results = [];

        if ($cfg['enabled'] && $cfg['meta_pixel'] && $cfg['meta_token']) {
            $results['meta'] = $this->sendToMeta($event, $payload, $userData, $customer, $cfg);
        }

        if ($cfg['enabled'] && $cfg['tiktok_pixel'] && $cfg['tiktok_token']) {
            $results['tiktok'] = $this->sendToTikTok($event, $payload, $userData, $customer, $cfg);
        }

        if (empty($results)) {
            $results['skipped'] = 'Medición desactivada o sin credenciales de API.';
        }

        return $results;
    }

    // ══════════════════════════════════════════════════════════════
    // Meta — Conversions API
    // ══════════════════════════════════════════════════════════════

    private function sendToMeta(string $event, array $payload, array $userData, array $customer, array $cfg): array
    {
        $user = array_filter([
            'client_ip_address' => $userData['ip']         ?? null,
            'client_user_agent' => $userData['user_agent'] ?? null,
            'fbc'               => $userData['fbc']        ?? null,
            'fbp'               => $userData['fbp']        ?? null,
            'em'                => $this->hashEmail($customer['email'] ?? null),
            'ph'                => $this->hashPhone($customer['phone'] ?? null),
        ]);

        // Meta espera los identificadores personales como array de hashes.
        foreach (['em', 'ph'] as $k) {
            if (isset($user[$k])) {
                $user[$k] = [$user[$k]];
            }
        }

        $body = [
            'data' => [array_filter([
                'event_name'       => self::EVENT_MAP[$event]['meta'],
                'event_time'       => time(),
                'event_id'         => $payload['event_id'] ?? null,
                'event_source_url' => $payload['url'] ?? null,
                'action_source'    => 'website',
                'user_data'        => $user,
                'custom_data'      => array_filter([
                    'currency'     => $payload['currency'] ?? 'PEN',
                    'value'        => $payload['value'] ?? null,
                    'content_type' => 'product',
                    'contents'     => $this->metaContents($payload['items'] ?? []),
                ], fn ($v) => $v !== null && $v !== []),
            ], fn ($v) => $v !== null && $v !== [])],
        ];

        // test_event_code deja ver el evento en el Test Events de Meta, pero
        // mientras esté puesto NO cuenta como conversión. Se vacía al acabar.
        if ($cfg['test_code']) {
            $body['test_event_code'] = $cfg['test_code'];
        }

        $url = sprintf(self::META_ENDPOINT, self::META_VERSION, $cfg['meta_pixel']);

        try {
            $res = Http::timeout(self::TIMEOUT)
                ->asJson()
                ->post($url . '?access_token=' . urlencode($cfg['meta_token']), $body);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'red: ' . $e->getMessage()];
        }

        $json = $res->json() ?? [];

        // Un 200 NO significa entregado. Meta responde 200 con
        // events_received = 0 cuando descarta el evento (por ejemplo, un `fbc`
        // mal formado). Esto es exactamente lo que R15 pide verificar.
        $received = (int) ($json['events_received'] ?? 0);

        if (!$res->successful() || $received < 1) {
            Log::warning('Meta CAPI no entregó el evento', [
                'event'    => $event,
                'status'   => $res->status(),
                'received' => $received,
                'response' => mb_substr($res->body(), 0, 500),
            ]);

            return [
                'ok'       => false,
                'reason'   => $json['error']['message'] ?? 'events_received = 0',
                'received' => $received,
            ];
        }

        return ['ok' => true, 'received' => $received];
    }

    /** `contents` de Meta: id + quantity + item_price. */
    private function metaContents(array $items): array
    {
        return array_values(array_map(fn ($i) => [
            'id'         => $i['content_id'] ?? '',
            'quantity'   => (int) ($i['quantity'] ?? 1),
            'item_price' => (float) ($i['price'] ?? 0),
        ], $items));
    }

    // ══════════════════════════════════════════════════════════════
    // TikTok — Events API v1.3
    // ══════════════════════════════════════════════════════════════

    private function sendToTikTok(string $event, array $payload, array $userData, array $customer, array $cfg): array
    {
        $body = [
            'event_source'    => 'web',
            'event_source_id' => $cfg['tiktok_pixel'],
            'data'            => [array_filter([
                'event'      => self::EVENT_MAP[$event]['tiktok'],
                'event_time' => time(),
                'event_id'   => $payload['event_id'] ?? null,
                'user'       => array_filter([
                    'email'      => $this->hashEmail($customer['email'] ?? null),
                    'phone'      => $this->hashPhone($customer['phone'] ?? null),
                    'ip'         => $userData['ip']         ?? null,
                    'user_agent' => $userData['user_agent'] ?? null,
                    'ttclid'     => $userData['ttclid']     ?? null,
                ]),
                'page'       => array_filter(['url' => $payload['url'] ?? null]),
                'properties' => array_filter([
                    'currency'     => $payload['currency'] ?? 'PEN',
                    'value'        => $payload['value'] ?? null,
                    'content_type' => 'product',
                    'contents'     => $this->tiktokContents($payload['items'] ?? []),
                ], fn ($v) => $v !== null && $v !== []),
            ], fn ($v) => $v !== null && $v !== [])],
        ];

        try {
            $res = Http::timeout(self::TIMEOUT)
                ->withHeaders(['Access-Token' => $cfg['tiktok_token']])
                ->asJson()
                ->post(self::TIKTOK_ENDPOINT, $body);
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'red: ' . $e->getMessage()];
        }

        $json = $res->json() ?? [];

        // TikTok devuelve 200 con `code` distinto de 0 cuando rechaza el
        // evento. El código HTTP por sí solo no dice nada (R15).
        $code = (int) ($json['code'] ?? -1);

        if (!$res->successful() || $code !== 0) {
            Log::warning('TikTok Events API no entregó el evento', [
                'event'    => $event,
                'status'   => $res->status(),
                'code'     => $code,
                'response' => mb_substr($res->body(), 0, 500),
            ]);

            return [
                'ok'     => false,
                'reason' => $json['message'] ?? ('code ' . $code),
                'code'   => $code,
            ];
        }

        return ['ok' => true, 'code' => $code];
    }

    /** `contents` de TikTok: content_id + quantity + price. */
    private function tiktokContents(array $items): array
    {
        return array_values(array_map(fn ($i) => [
            'content_id'   => $i['content_id'] ?? '',
            'content_name' => $i['content_name'] ?? '',
            'quantity'     => (int) ($i['quantity'] ?? 1),
            'price'        => (float) ($i['price'] ?? 0),
        ], $items));
    }

    // ══════════════════════════════════════════════════════════════
    // Hashes — las dos plataformas piden SHA-256 del dato normalizado
    // ══════════════════════════════════════════════════════════════

    private function hashEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? hash('sha256', $email) : null;
    }

    /**
     * Teléfono en E.164 sin el `+`. Un número peruano suelto son 9 dígitos y
     * sin el 51 delante las plataformas no lo casan con nadie.
     */
    private function hashPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || strlen($digits) < 8) {
            return null;
        }

        if (strlen($digits) === 9 && $digits[0] === '9') {
            $digits = '51' . $digits;
        }

        return hash('sha256', $digits);
    }
}
