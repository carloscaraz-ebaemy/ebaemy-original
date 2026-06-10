<?php

namespace App\Console\Commands;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Services\Marketplace\FalabellaService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Verifica la conexión a Saga Falabella (Seller Center) por tenant.
 *
 * Las credenciales viven en la base de CADA tenant (marketplace_channels,
 * platform='falabella'). Este comando recorre los tenants, hace una llamada
 * firmada real (GetProducts) y reporta si la API Key del tenant es válida.
 *
 * Uso:
 *   php artisan marketplace:falabella-test                # todos los tenants con Saga
 *   php artisan marketplace:falabella-test --tenant=uuid  # uno solo
 */
class FalabellaConnectionTest extends Command
{
    protected $signature = 'marketplace:falabella-test
                            {--tenant= : UUID de un website específico}';

    protected $description = 'Prueba la conexión a Saga Falabella (Seller Center) por tenant';

    public function handle(): int
    {
        $uuid = $this->option('tenant');

        $websites = $uuid
            ? Website::where('uuid', $uuid)->get()
            : Website::all();

        if ($websites->isEmpty()) {
            $this->error('No se encontraron tenants.');
            return self::FAILURE;
        }

        $rows = [];

        foreach ($websites as $w) {
            app(Environment::class)->tenant($w);

            try {
                $channel = MarketplaceChannel::platform('falabella')->first();
            } catch (\Throwable $e) {
                // Tenant sin tablas de marketplace todavía
                continue;
            }

            if (!$channel) {
                continue; // este tenant no tiene cuenta Saga configurada
            }

            $userId  = $channel->getCredential('user_id', '');
            $hasKey  = $channel->getCredential('api_key', '') !== '';
            $mapped  = MarketplaceProduct::where('channel_id', $channel->id)->count();

            if (!$hasKey || $userId === '') {
                $rows[] = [$w->uuid, $userId ?: '—', $channel->status, '⚠ Sin credenciales', $mapped];
                continue;
            }

            $service = new FalabellaService($channel);
            $result  = $service->testConnection();

            $status = $result['success']
                ? '✓ OK'
                : '✗ ' . \Illuminate\Support\Str::limit($result['message'] ?? 'Error', 50);

            // Persistir el resultado para que se vea en el panel
            if ($result['success']) {
                $channel->markSynced();
            } else {
                $channel->markError($result['message'] ?? 'Error de conexión');
            }

            $rows[] = [$w->uuid, $userId, $channel->status, $status, $mapped];
        }

        if (empty($rows)) {
            $this->warn('Ningún tenant tiene un canal Saga Falabella configurado.');
            return self::SUCCESS;
        }

        $this->table(
            ['Tenant (uuid)', 'User ID', 'Estado canal', 'Conexión', 'Productos mapeados'],
            $rows
        );

        return self::SUCCESS;
    }
}
