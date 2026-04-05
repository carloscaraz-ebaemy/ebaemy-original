<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Webhook;
use App\Models\Tenant\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookDispatcher
{
    /**
     * Eventos soportados.
     */
    const EVENTS = [
        'order.created',
        'order.status_changed',
        'order.cancelled',
        'document.created',
        'item.created',
        'item.updated',
        'item.stock_changed',
        'customer.created',
        'payment.received',
    ];

    /**
     * Disparar un evento a todos los webhooks suscritos.
     */
    public static function dispatch(string $event, array $data = []): void
    {
        $webhooks = Webhook::active()->get()->filter(fn($w) => $w->subscribesTo($event));

        foreach ($webhooks as $webhook) {
            try {
                self::send($webhook, $event, $data);
            } catch (\Throwable $e) {
                Log::warning("Webhook dispatch failed", [
                    'webhook_id' => $webhook->id,
                    'event'      => $event,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Disparar de forma asíncrona (para no bloquear la request principal).
     */
    public static function dispatchAsync(string $event, array $data = []): void
    {
        dispatch(function () use ($event, $data) {
            self::dispatch($event, $data);
        })->afterResponse();
    }

    private static function send(Webhook $webhook, string $event, array $data): void
    {
        $payload = [
            'event'      => $event,
            'timestamp'  => now()->toIso8601String(),
            'data'       => $data,
        ];

        $headers = [
            'Content-Type'     => 'application/json',
            'X-Webhook-Event'  => $event,
        ];

        // Firma HMAC si tiene secret
        if ($webhook->secret) {
            $signature = hash_hmac('sha256', json_encode($payload), $webhook->secret);
            $headers['X-Webhook-Signature'] = $signature;
        }

        $start = microtime(true);

        try {
            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->post($webhook->url, $payload);

            $duration = (int) ((microtime(true) - $start) * 1000);
            $success  = $response->successful();

            WebhookLog::create([
                'webhook_id'      => $webhook->id,
                'event'           => $event,
                'payload'         => $payload,
                'response_status' => $response->status(),
                'response_body'   => substr($response->body(), 0, 2000),
                'duration_ms'     => $duration,
                'success'         => $success,
                'created_at'      => now(),
            ]);

            $webhook->last_triggered_at = now();
            if ($success) {
                $webhook->failure_count = 0;
                $webhook->last_error = null;
            } else {
                $webhook->failure_count++;
                $webhook->last_failed_at = now();
                $webhook->last_error = "HTTP {$response->status()}";
                // Auto-desactivar después de 10 fallos consecutivos
                if ($webhook->failure_count >= 10) {
                    $webhook->is_active = false;
                }
            }
            $webhook->save();

        } catch (\Throwable $e) {
            $duration = (int) ((microtime(true) - $start) * 1000);

            WebhookLog::create([
                'webhook_id'      => $webhook->id,
                'event'           => $event,
                'payload'         => $payload,
                'response_status' => null,
                'response_body'   => $e->getMessage(),
                'duration_ms'     => $duration,
                'success'         => false,
                'created_at'      => now(),
            ]);

            $webhook->failure_count++;
            $webhook->last_failed_at = now();
            $webhook->last_error = substr($e->getMessage(), 0, 500);
            if ($webhook->failure_count >= 10) {
                $webhook->is_active = false;
            }
            $webhook->save();
        }
    }
}
