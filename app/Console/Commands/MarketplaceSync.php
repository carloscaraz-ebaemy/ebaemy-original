<?php

namespace App\Console\Commands;

use App\Services\Marketplace\MarketplaceOrchestrator;
use App\Models\Tenant\MarketplaceChannel;
use App\Services\Marketplace\MetaFeedService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

class MarketplaceSync extends Command
{
    protected $signature = 'marketplace:sync
        {action=all : products|stock|prices|orders|feed|all}
        {--platform= : falabella|meta|all}
        {--channel= : Channel ID specific}
        {--tenant= : UUID de un website específico (por defecto: todos)}';

    protected $description = 'Sincronizar marketplace (Falabella, Meta) — recorre todos los tenants';

    public function handle(): void
    {
        $action = $this->argument('action');
        $platform = $this->option('platform') ?: 'all';

        $this->info("Marketplace sync: {$action} [{$platform}]");

        $env = app(Environment::class);

        // Si ya hay un tenant activo (ej. se invoca dentro de otro flujo tenant),
        // procesamos sólo ese. Si no (cron del sistema), recorremos todos.
        if ($env->tenant()) {
            $this->processTenant($env->tenant(), $action, $platform);
            $this->info('Done.');
            return;
        }

        $uuid = $this->option('tenant');
        $websites = $uuid ? Website::where('uuid', $uuid)->get() : Website::all();

        if ($websites->isEmpty()) {
            $this->warn('No se encontraron tenants.');
            return;
        }

        foreach ($websites as $w) {
            $env->tenant($w);
            $this->processTenant($w, $action, $platform);
        }

        $this->info('Done.');
    }

    /**
     * Procesa los canales de un tenant ya activado.
     */
    protected function processTenant(Website $website, string $action, string $platform): void
    {
        try {
            $channels = MarketplaceChannel::active()
                ->when($platform !== 'all', fn($q) => $q->where('platform', $platform))
                ->when($this->option('channel'), fn($q) => $q->where('id', $this->option('channel')))
                ->get();
        } catch (\Throwable $e) {
            // Tenant sin tablas de marketplace todavía — lo saltamos en silencio.
            return;
        }

        if ($channels->isEmpty()) {
            return;
        }

        $this->line("→ Tenant {$website->uuid}");

        foreach ($channels as $channel) {
            $this->line("  · {$channel->platform}: {$channel->name}");
            $service = MarketplaceOrchestrator::resolveService($channel);
            if (!$service) {
                $this->warn("    Sin servicio para: {$channel->platform}");
                continue;
            }

            try {
                match ($action) {
                    'products' => $this->runAndReport($service, 'syncProducts'),
                    'stock' => $this->runAndReport($service, 'syncStock'),
                    'prices' => $this->runAndReport($service, 'syncPrices'),
                    'orders' => $this->runAndReport($service, 'fetchOrders'),
                    'feed' => $this->generateFeed($channel),
                    'all' => $this->runAll($service, $channel),
                };
                $channel->markSynced();
            } catch (\Throwable $e) {
                $this->error("    Error: {$e->getMessage()}");
                $channel->markError($e->getMessage());
            }
        }
    }

    protected function runAndReport($service, string $method): void
    {
        if (!method_exists($service, $method)) {
            $this->warn("    Method {$method} not available");
            return;
        }
        $result = $service->$method();
        $processed = $result['items_processed'] ?? $result['processed'] ?? 0;
        $success = $result['items_success'] ?? $result['success'] ?? 0;
        $failed = $result['items_failed'] ?? $result['failed'] ?? 0;
        $this->info("    {$method}: {$processed} processed, {$success} OK, {$failed} failed");
    }

    protected function generateFeed(MarketplaceChannel $channel): void
    {
        if ($channel->platform === 'meta') {
            $service = new MetaFeedService($channel);
            $service->generateXmlFeed();
            $service->generateCsvFeed();
            $this->info('    Feed XML + CSV generados en /storage/feeds/');
        }
    }

    protected function runAll($service, MarketplaceChannel $channel): void
    {
        if (method_exists($service, 'syncProducts')) $this->runAndReport($service, 'syncProducts');
        if (method_exists($service, 'syncStock')) $this->runAndReport($service, 'syncStock');
        if (method_exists($service, 'syncPrices')) $this->runAndReport($service, 'syncPrices');
        if (method_exists($service, 'fetchOrders')) $this->runAndReport($service, 'fetchOrders');
        if ($channel->platform === 'meta') $this->generateFeed($channel);
    }
}
