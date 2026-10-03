<?php

namespace App\Jobs\Marketplace;

use App\Services\Marketplace\AdsConversionApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Manda una conversión a Meta y TikTok fuera del camino de la petición.
 *
 * Por qué a cola y no en el controlador: son dos APIs externas, y el evento
 * que más importa (`Purchase`) ocurre justo al confirmar el pedido. Un
 * timeout de Meta no puede colgar una compra ni dejar al comprador mirando
 * una pantalla en blanco.
 *
 * Todo lo que el envío necesita viaja en el constructor como datos planos: el
 * worker no tiene la sesión del comprador ni su request, así que la IP, el
 * user agent y los click ids se recogen ANTES, en la petición.
 *
 * No recibe modelos. Serializar un MarketplaceOrder aquí haría que el job
 * recargue la fila desde la base al ejecutarse, y con ella el precio de ese
 * momento — que puede no ser el que el comprador pagó.
 */
class SendAdsConversion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Cuatro intentos espaciados. Más no tiene sentido: las dos plataformas
     * descartan eventos con más de 7 días, y si fallan cuatro veces en una
     * hora el problema es la credencial, no la red.
     */
    public $tries = 4;

    public $backoff = [30, 300, 1800];

    public function __construct(
        public string $event,
        public array $payload,
        public array $userData = [],
        public array $customer = [],
    ) {}

    public function handle(AdsConversionApi $api): void
    {
        $results = $api->send($this->event, $this->payload, $this->userData, $this->customer);

        // Verificamos la ENTREGA, no el encolado (R16) ni el código HTTP (R15).
        // `send()` ya mira `events_received` de Meta y el `code` de TikTok; aquí
        // sólo decidimos si hay que reintentar.
        $failed = collect($results)
            ->filter(fn ($r) => is_array($r) && ($r['ok'] ?? false) === false)
            ->keys();

        if ($failed->isNotEmpty()) {
            Log::warning('SendAdsConversion: entrega incompleta', [
                'event'     => $this->event,
                'event_id'  => $this->payload['event_id'] ?? null,
                'platforms' => $failed->all(),
                'results'   => $results,
            ]);

            // Un fallo de credencial no se arregla reintentando, y repetir el
            // intento sólo llena el log. Reintentamos sólo lo que puede ser
            // transitorio (red o 5xx), que es lo que `reason` marca como red.
            $transient = collect($results)->contains(
                fn ($r) => is_array($r) && str_starts_with((string) ($r['reason'] ?? ''), 'red:')
            );

            if ($transient) {
                $this->release(60);
            }
        }
    }

    /**
     * Que una conversión no llegue a la plataforma es un problema de medición,
     * nunca del pedido: el pedido ya está creado y cobrado. Se registra y se
     * deja morir el job.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('SendAdsConversion falló definitivamente', [
            'event'    => $this->event,
            'event_id' => $this->payload['event_id'] ?? null,
            'error'    => $e->getMessage(),
        ]);
    }
}
