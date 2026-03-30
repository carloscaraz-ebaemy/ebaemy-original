<?php

namespace App\Console\Commands;

use App\Services\WarehouseEtl;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command: warehouse:sync-etl
 *
 * Ejecuta el ETL incremental desde todos los tenants (o uno específico)
 * hacia el data warehouse analítico (BD 'warehouse').
 *
 * Uso típico:
 *   php artisan warehouse:sync-etl                        # ayer → hoy, sin items
 *   php artisan warehouse:sync-etl --with-items           # incluye snapshot de catálogo
 *   php artisan warehouse:sync-etl --from=2026-01-01 --to=2026-03-24 --with-items
 *   php artisan warehouse:sync-etl --tenant=<uuid>        # solo un tenant
 *   php artisan warehouse:sync-etl --dry-run              # simula sin escribir
 */
class EtlSyncWarehouse extends Command
{
    protected $signature = 'warehouse:sync-etl
        {--tenant= : UUID del tenant a sincronizar (omitir = todos)}
        {--from=   : Fecha inicio ISO (Y-m-d). Default: ayer}
        {--to=     : Fecha fin ISO (Y-m-d). Default: hoy}
        {--with-items : Incluir snapshot de catálogo de items}
        {--dry-run    : Mostrar qué se procesaría sin escribir al warehouse}';

    protected $description = 'Sincroniza datos de tenants al data warehouse analítico (ETL incremental).';

    public function __construct(private readonly WarehouseEtl $etl)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $from      = $this->option('from') ?? now()->subDay()->toDateString();
        $to        = $this->option('to')   ?? now()->toDateString();
        $withItems = (bool) $this->option('with-items');
        $dryRun    = (bool) $this->option('dry-run');
        $tenantUuid = $this->option('tenant');

        if (!$this->validateDates($from, $to)) {
            return self::FAILURE;
        }

        $this->info(sprintf(
            '[warehouse:sync-etl] Rango: %s → %s | items: %s | dry-run: %s',
            $from, $to,
            $withItems ? 'sí' : 'no',
            $dryRun    ? 'SÍ' : 'no'
        ));

        if ($dryRun) {
            $this->warn('Modo dry-run: no se escribirá nada al warehouse.');
        }

        $totals  = ['inserted' => 0, 'updated' => 0, 'errors' => 0, 'tenants' => 0];
        $failed  = [];

        $query = Website::query();
        if ($tenantUuid) {
            $query->where('uuid', $tenantUuid);
        }

        $query->chunk(10, function ($websites) use ($from, $to, $withItems, $dryRun, &$totals, &$failed) {
            foreach ($websites as $website) {
                $this->line("  → tenant: {$website->uuid}");

                if ($dryRun) {
                    $totals['tenants']++;
                    continue;
                }

                try {
                    $stats = $this->etl->syncTenant($website, $from, $to, $withItems);
                    $totals['inserted'] += $stats['inserted'];
                    $totals['updated']  += $stats['updated'];
                    $totals['errors']   += $stats['errors'];
                    $totals['tenants']++;

                    $this->line(sprintf(
                        '     ✓ inserted=%d updated=%d errors=%d',
                        $stats['inserted'], $stats['updated'], $stats['errors']
                    ));
                } catch (\Throwable $e) {
                    $totals['errors']++;
                    $failed[] = $website->uuid;
                    $this->error("     ✗ Error: {$e->getMessage()}");
                    Log::error('[EtlSyncWarehouse] Tenant failed.', [
                        'tenant' => $website->uuid,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }
        });

        $this->newLine();
        $this->info(sprintf(
            'ETL completado. Tenants: %d | Insertados: %d | Actualizados: %d | Errores: %d',
            $totals['tenants'], $totals['inserted'], $totals['updated'], $totals['errors']
        ));

        if ($failed) {
            $this->warn('Tenants con error: ' . implode(', ', $failed));
        }

        return $totals['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function validateDates(string $from, string $to): bool
    {
        if (!strtotime($from) || !strtotime($to)) {
            $this->error("Fechas inválidas: from={$from} to={$to}");
            return false;
        }

        if ($from > $to) {
            $this->error("--from ({$from}) no puede ser mayor que --to ({$to})");
            return false;
        }

        return true;
    }
}
