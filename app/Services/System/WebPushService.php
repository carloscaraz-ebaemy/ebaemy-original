<?php

namespace App\Services\System;

use App\Models\System\PushSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/**
 * Envío de Web Push a compradores del marketplace.
 *
 * Usa minishlink/web-push con VAPID (config/webpush.php).
 * Limpia automáticamente las suscripciones expiradas (410 Gone) para que
 * la tabla no acumule endpoints muertos.
 *
 * Payload estándar (lo lee el SW en sw-marketplace.js → push handler):
 *   ['title' => '', 'body' => '', 'url' => '/marketplace/...', 'icon' => '', 'tag' => '']
 */
class WebPushService
{
    /**
     * Envía a una colección de PushSubscription. Devuelve conteo.
     *
     * @return array{sent:int, failed:int, expired:int}
     */
    public function send(Collection $subscriptions, array $payload): array
    {
        $public  = config('webpush.public_key');
        $private = config('webpush.private_key');

        if (empty($public) || empty($private)) {
            Log::warning('[WebPush] VAPID keys no configuradas — no se envía nada.');
            return ['sent' => 0, 'failed' => 0, 'expired' => 0];
        }

        if ($subscriptions->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'expired' => 0];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => config('webpush.subject'),
                'publicKey'  => $public,
                'privateKey' => $private,
            ],
        ]);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        // Mapa endpoint → id para limpiar expiradas tras el flush
        $byEndpoint = [];
        foreach ($subscriptions as $sub) {
            $byEndpoint[$sub->endpoint] = $sub->id;
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->public_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding ?: 'aes128gcm',
                ]),
                $json
            );
        }

        $sent = 0; $failed = 0; $expiredIds = [];

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getEndpoint();
            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
                // 404/410 → suscripción muerta, marcar para borrar
                if ($report->isSubscriptionExpired() && isset($byEndpoint[$endpoint])) {
                    $expiredIds[] = $byEndpoint[$endpoint];
                }
            }
        }

        if (!empty($expiredIds)) {
            PushSubscription::whereIn('id', $expiredIds)->delete();
        }

        // Marcar last_used_at de las que funcionaron
        if ($sent > 0) {
            PushSubscription::whereIn('endpoint', array_keys($byEndpoint))
                ->whereNotIn('id', $expiredIds)
                ->update(['last_used_at' => now()]);
        }

        return ['sent' => $sent, 'failed' => $failed, 'expired' => count($expiredIds)];
    }

    /**
     * Envía a todas las suscripciones de un comprador (todos sus dispositivos).
     */
    public function sendToUser(int $marketplaceUserId, array $payload): array
    {
        $subs = PushSubscription::where('marketplace_user_id', $marketplaceUserId)->get();
        return $this->send($subs, $payload);
    }

    /**
     * Broadcast a todas las suscripciones (usar con cuidado — campañas).
     */
    public function sendToAll(array $payload): array
    {
        return $this->send(PushSubscription::query()->get(), $payload);
    }
}
