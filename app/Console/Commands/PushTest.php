<?php

namespace App\Console\Commands;

use App\Models\System\PushSubscription;
use App\Services\System\WebPushService;
use Illuminate\Console\Command;

/**
 * Envía una notificación push de prueba. Valida el pipeline completo
 * (VAPID + suscripción + entrega) sin tocar el checkout.
 *
 *   php artisan push:test                 # a TODAS las suscripciones
 *   php artisan push:test --user=5        # a un comprador específico
 */
class PushTest extends Command
{
    protected $signature   = 'push:test {--user= : marketplace_user_id destino}';
    protected $description = 'Envía un Web Push de prueba (valida VAPID + suscripciones)';

    public function handle(WebPushService $push): int
    {
        $total = PushSubscription::count();
        $this->line("Suscripciones registradas: <comment>{$total}</comment>");

        if ($total === 0) {
            $this->warn('No hay suscripciones. Suscribite primero desde el navegador con window.ebaemyEnablePush().');
            return self::SUCCESS;
        }

        $payload = [
            'title' => 'ebaemy Marketplace',
            'body'  => 'Notificaciones activadas correctamente. ¡Esto es una prueba!',
            'url'   => '/marketplace',
            'icon'  => '/images/icon-192.png',
            'tag'   => 'ebaemy-test',
        ];

        $userId = $this->option('user');
        if ($userId) {
            $result = $push->sendToUser((int) $userId, $payload);
            $this->info("Enviado a user {$userId}: " . json_encode($result));
        } else {
            $result = $push->sendToAll($payload);
            $this->info('Enviado a todas: ' . json_encode($result));
        }

        if (($result['sent'] ?? 0) === 0 && ($result['failed'] ?? 0) === 0) {
            $this->warn('No se envió nada. ¿Configuraste VAPID_PUBLIC_KEY/PRIVATE_KEY en .env?');
        }

        return self::SUCCESS;
    }
}
