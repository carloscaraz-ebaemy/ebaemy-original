<?php

namespace App\Console\Commands;

use App\Services\Marketplace\MarketplaceOrchestrator;
use App\Models\Tenant\MarketplaceChannel;
use App\Services\Marketplace\MetaFeedService;
use Illuminate\Console\Command;

class MarketplaceSync extends Command
{
    protected $signature = 'marketplace:sync
        {action=all : products|stock|prices|orders|feed|all}
        {--platform= : falabella|meta|all}
        {--channel= : Channel ID specific}';

    protected $description = 'Sincronizar marketplace (Falabella, Meta)';

    public function handle(): void
    {
        $action = $this->argument('action');
        $platform = $this->option('platform') ?: 'all';

        $this->info("Marketplace sync: {$action} [{$platform}]");

        // Este comando SOLO sirve para integraciones externas (Falabella, Meta).
        // MarketplaceChannel es modelo de tenant — requiere contexto activo.
        // Si nos llaman sin tenant (ej. cron system) damos hint claro en vez
        // del confuso 'Database connection [tenant] not configured'.
        try {
            $channels = MarketplaceChannel::active()
                ->when($platform !== 'all', fn($q) => $q->where('platform', $platform))
                ->when($this->option('channel'), fn($q) => $q->where('id', $this->option('channel')))
                ->get();
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), "Database connection [tenant] not configured")) {
                $this->warn('Este comando (marketplace:sync) es para integraciones externas');
                $this->warn('(Falabella, Meta) y debe correrse desde un contexto tenant.');
                $this->newLine();
                $this->info('Para sincronizar el marketplace INTERNO de ebaemy.com usa:');
                $this->info('  php artisan ebaemy-marketplace:sync                   # todos los tenants');
                $this->info('  php artisan ebaemy-marketplace:sync --client=1        # un tenant especifico');
                return;
            }
            throw $e;
        }

        if ($channels->isEmpty()) {
            $this->warn('No active marketplace channels found.');
            return;
        }

        foreach ($channels as $channel) {
            $this->line("  → {$channel->platform}: {$channel->name}");
            $service = MarketplaceOrchestrator::resolveService($channel);
            if (!$service) {
                $this->warn("    No service for platform: {$channel->platform}");
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

        $this->info('Done.');
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
