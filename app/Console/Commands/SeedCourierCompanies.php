<?php

namespace App\Console\Commands;

use App\Services\Tenant\CourierCompanyCatalog;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Aplica el catálogo extendido de empresas de transporte / courier en
 * Perú a TODOS los tenants existentes (o uno específico con --tenant).
 *
 * Idempotente: si la agencia ya existe (por nombre), la deja intacta.
 *
 *   php artisan couriers:seed-tenants
 *   php artisan couriers:seed-tenants --tenant=alasitas
 *   php artisan couriers:seed-tenants --dry-run
 */
class SeedCourierCompanies extends Command
{
    protected $signature = 'couriers:seed-tenants
                            {--tenant= : UUID/subdominio específico (opcional)}
                            {--dry-run : Solo mostrar qué se insertaría}';

    protected $description = 'Sembrar el catálogo extendido de couriers/agencias de transporte en todos los tenants';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only   = $this->option('tenant');

        $query = Website::query();
        if ($only) {
            $query->where('uuid', $only);
        }
        $websites = $query->get();

        if ($websites->isEmpty()) {
            $this->error('No se encontraron tenants.');
            return self::FAILURE;
        }

        $this->info(sprintf(
            'Procesando %d tenant(s)%s...',
            $websites->count(),
            $dryRun ? ' [DRY-RUN]' : ''
        ));

        $env = app(Environment::class);
        $totalInserted = 0;

        foreach ($websites as $website) {
            $env->tenant($website);
            $this->line("\n--- {$website->uuid} ---");

            try {
                if ($dryRun) {
                    $count = $this->countMissing();
                    $this->line("  Faltan: {$count} agencias por insertar");
                } else {
                    $inserted = CourierCompanyCatalog::apply('tenant');
                    $totalInserted += $inserted;
                    $this->info("  ✓ {$inserted} insertada(s)");
                }
            } catch (\Throwable $e) {
                $this->error("  ✗ {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? 'Dry-run completado.'
            : "Listo. Total insertadas: {$totalInserted}"
        );

        return self::SUCCESS;
    }

    /**
     * Cuenta cuántas agencias del catálogo faltan en el tenant activo.
     * Solo se usa en --dry-run.
     */
    private function countMissing(): int
    {
        $entries  = CourierCompanyCatalog::entries();
        $existing = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('courier_companies')
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $n))))
            ->all();
        $set = array_flip($existing);

        $missing = 0;
        foreach ($entries as $e) {
            $key = mb_strtolower(trim(preg_replace('/\s+/', ' ', $e['name'])));
            if (!isset($set[$key])) $missing++;
        }
        return $missing;
    }
}
