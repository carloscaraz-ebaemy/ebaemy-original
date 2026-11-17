<?php

namespace App\Console\Commands;

use App\Models\Tenant\MarketplaceChannel;
use App\Services\Marketplace\FalabellaService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Deja el stock del ERP igual al de Saga Falabella (Saga manda).
 *
 * Sin --apply solo muestra las diferencias. Con --apply ajusta cada producto
 * con su movimiento en el kardex. Solo LEE de Saga, nunca le escribe.
 *
 *   php artisan marketplace:falabella-stock-pull --tenant=UUID
 *   php artisan marketplace:falabella-stock-pull --tenant=UUID --apply
 */
class FalabellaStockPull extends Command
{
    protected $signature = 'marketplace:falabella-stock-pull
                            {--tenant= : UUID del website (obligatorio)}
                            {--apply : Ajusta el ERP; sin esto solo muestra las diferencias}';

    protected $description = 'Alinea el stock del ERP con el de Saga Falabella (solo lee de Saga)';

    public function handle(): int
    {
        $website = Website::where('uuid', (string) $this->option('tenant'))->first();
        if (!$website) {
            $this->error('Falta --tenant=UUID o no existe.');
            return self::FAILURE;
        }
        app(Environment::class)->tenant($website);

        $channel = MarketplaceChannel::platform('falabella')->first();
        if (!$channel) {
            $this->error('El tenant no tiene canal Saga Falabella.');
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $r = (new FalabellaService($channel))->pullStock($apply);

        if (($r['status'] ?? null) === 'error') {
            $this->error('Error: ' . ($r['details']['error'] ?? '?'));
            return self::FAILURE;
        }
        $cambios = $r['details']['cambios'] ?? [];
        $comparados = $r['processed'] ?? $r['items_processed'] ?? 0;
        $fallidos = $r['failed'] ?? $r['items_failed'] ?? 0;
        $this->info(($apply ? 'Ajustado' : 'Simulación') . ": {$comparados} SKUs comparados, "
            . ($r['details']['diferencias'] ?? 0) . ' con diferencia (se muestran hasta 50), ' . $fallidos . ' fallidos.');

        if ($cambios) {
            $this->table(['SKU', 'ERP', 'Saga'], array_map(fn ($c) => [$c['sku'], $c['erp'], $c['saga']], $cambios));
        }
        foreach ($r['details']['errores'] ?? [] as $e) {
            $this->warn("  {$e['sku']}: {$e['error']}");
        }

        return self::SUCCESS;
    }
}
