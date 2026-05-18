<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aplica manualmente las migraciones de Fase 1 rediseño pricing
 * (2026_05_18_000001/2/3) en TODOS los tenants.
 *
 * Existe porque `tenancy:migrate --force` no detectó los archivos nuevos
 * en producción aunque están en database/migrations/tenant/ (causa
 * desconocida — posiblemente cache del descubridor del paquete hyn).
 *
 * Idempotente: verifica `Schema::hasColumn`/`hasTable` antes de aplicar.
 * Re-ejecutable sin riesgo.
 *
 * Uso:
 *   php artisan pricing:phase1-install            # dry-run (solo reporta)
 *   php artisan pricing:phase1-install --apply    # aplica los cambios
 */
class PricingPhase1Install extends Command
{
    protected $signature = 'pricing:phase1-install {--apply : Ejecutar las migraciones (sin esto solo reporta estado)}';
    protected $description = 'Aplica Fase 1 rediseño pricing en todos los tenants (workaround tenancy:migrate)';

    private const MIGRATIONS = [
        '2026_05_18_000001_extend_items_pricing_fields',
        '2026_05_18_000002_extend_item_price_history_audit_cost',
        '2026_05_18_000003_create_pricing_settings_table',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Modo APPLY — se ejecutarán migraciones.' : 'Modo DRY-RUN — solo reporta estado actual.');
        $this->line('');

        $hostnames = Hostname::with('website')->get();
        $tenancy   = app(Environment::class);
        $originalT = $tenancy->tenant();

        $summary = ['ok' => 0, 'applied' => 0, 'errors' => 0];

        foreach ($hostnames as $hn) {
            if (!$hn->website) {
                continue;
            }

            try {
                $tenancy->tenant($hn->website);
                $result = $this->processTenant($hn->fqdn, $apply);

                if ($result === 'already') {
                    $summary['ok']++;
                } elseif ($result === 'applied') {
                    $summary['applied']++;
                }
            } catch (\Throwable $e) {
                $summary['errors']++;
                $this->error("  {$hn->fqdn}: ERROR — " . $e->getMessage());
            }
        }

        $tenancy->tenant($originalT ?: null);

        $this->line('');
        $this->info("Resumen: ya-OK={$summary['ok']} · aplicados={$summary['applied']} · errores={$summary['errors']}");

        if (!$apply) {
            $this->line('');
            $this->warn('Esto fue dry-run. Para aplicar: php artisan pricing:phase1-install --apply');
        }

        return $summary['errors'] === 0 ? 0 : 1;
    }

    /**
     * Devuelve 'already' si todo está aplicado, 'applied' si se aplicó algo,
     * lanza excepción si falló.
     */
    private function processTenant(string $fqdn, bool $apply): string
    {
        $hasNewCols = Schema::connection('tenant')->hasColumn('items', 'floor_price');
        $hasNewTable = Schema::connection('tenant')->hasTable('pricing_settings');
        $hasCostAudit = Schema::connection('tenant')->hasColumn('item_price_history', 'old_cost');

        if ($hasNewCols && $hasNewTable && $hasCostAudit) {
            $this->line("  <fg=green>✓</> {$fqdn}: ya aplicado");
            $this->registerMigrationsIfMissing();
            return 'already';
        }

        if (!$apply) {
            $missing = [];
            if (!$hasNewCols)   $missing[] = 'items.floor_price';
            if (!$hasNewTable)  $missing[] = 'pricing_settings';
            if (!$hasCostAudit) $missing[] = 'item_price_history.old_cost';
            $this->line("  <fg=yellow>○</> {$fqdn}: falta aplicar — " . implode(', ', $missing));
            return 'pending';
        }

        // 1) Extender items
        if (!Schema::connection('tenant')->hasColumn('items', 'floor_price')) {
            Schema::connection('tenant')->table('items', function (Blueprint $t) {
                if (!Schema::connection('tenant')->hasColumn('items', 'landed_cost_extra_pct')) {
                    $t->decimal('landed_cost_extra_pct', 5, 2)->default(0)->after('purchase_unit_price');
                }
                if (!Schema::connection('tenant')->hasColumn('items', 'target_margin_pct')) {
                    $t->decimal('target_margin_pct', 5, 2)->nullable()->after('landed_cost_extra_pct');
                }
                if (!Schema::connection('tenant')->hasColumn('items', 'min_margin_pct')) {
                    $t->decimal('min_margin_pct', 5, 2)->nullable()->after('target_margin_pct');
                }
                if (!Schema::connection('tenant')->hasColumn('items', 'compare_at_price')) {
                    $t->decimal('compare_at_price', 12, 4)->nullable()->after('sale_unit_price');
                }
                if (!Schema::connection('tenant')->hasColumn('items', 'floor_price')) {
                    $t->decimal('floor_price', 12, 4)->nullable()->after('compare_at_price');
                }
                if (!Schema::connection('tenant')->hasColumn('items', 'pricing_mode')) {
                    $t->enum('pricing_mode', ['margin', 'markup', 'manual'])->default('margin')->after('floor_price');
                }
                if (!Schema::connection('tenant')->hasColumn('items', 'liquidation_mode')) {
                    $t->boolean('liquidation_mode')->default(false)->after('pricing_mode');
                }
                if (!Schema::connection('tenant')->hasColumn('items', 'floor_price_recalc_at')) {
                    $t->timestamp('floor_price_recalc_at')->nullable()->after('liquidation_mode');
                }
            });

            // Migrar dato legacy
            if (Schema::connection('tenant')->hasColumn('items', 'percentage_of_profit')) {
                DB::connection('tenant')->statement(
                    'UPDATE items SET target_margin_pct = percentage_of_profit WHERE target_margin_pct IS NULL AND percentage_of_profit IS NOT NULL AND percentage_of_profit > 0'
                );
            }
        }

        // 2) Extender item_price_history
        if (!Schema::connection('tenant')->hasColumn('item_price_history', 'old_cost')) {
            Schema::connection('tenant')->table('item_price_history', function (Blueprint $t) {
                if (!Schema::connection('tenant')->hasColumn('item_price_history', 'old_cost')) {
                    $t->decimal('old_cost', 12, 4)->nullable()->after('new_price');
                }
                if (!Schema::connection('tenant')->hasColumn('item_price_history', 'new_cost')) {
                    $t->decimal('new_cost', 12, 4)->nullable()->after('old_cost');
                }
                if (!Schema::connection('tenant')->hasColumn('item_price_history', 'margin_at_change')) {
                    $t->decimal('margin_at_change', 5, 2)->nullable()->after('new_cost');
                }
                if (!Schema::connection('tenant')->hasColumn('item_price_history', 'change_type')) {
                    $t->enum('change_type', ['price', 'cost', 'both'])->default('price')->after('margin_at_change');
                }
            });

            // Hacer nullable old_price y new_price (para registros que solo documentan cambio de costo)
            try {
                Schema::connection('tenant')->table('item_price_history', function (Blueprint $t) {
                    $t->decimal('old_price', 12, 2)->nullable()->change();
                    $t->decimal('new_price', 12, 2)->nullable()->change();
                });
            } catch (\Throwable $e) {
                // Si doctrine/dbal no está, ignorar — los nuevos registros sin price siguen fallando hasta migrar.
                $this->line("    <fg=yellow>!</> {$fqdn}: no se pudo hacer nullable old/new_price ({$e->getMessage()})");
            }
        }

        // 3) Crear pricing_settings
        if (!Schema::connection('tenant')->hasTable('pricing_settings')) {
            Schema::connection('tenant')->create('pricing_settings', function (Blueprint $t) {
                $t->id();
                $t->decimal('default_min_margin_pct', 5, 2)->default(10);
                $t->boolean('block_sales_below_cost')->default(true);
                $t->boolean('audit_cost_changes')->default(true);
                $t->json('category_min_margins')->nullable();
                $t->timestamps();
            });

            DB::connection('tenant')->table('pricing_settings')->insert([
                'default_min_margin_pct' => 10,
                'block_sales_below_cost' => true,
                'audit_cost_changes'     => true,
                'category_min_margins'   => null,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        // 4) Registrar en migrations table para que tenancy:migrate no las re-corra
        $this->registerMigrationsIfMissing();

        $this->line("  <fg=green>+</> {$fqdn}: APLICADO");
        return 'applied';
    }

    private function registerMigrationsIfMissing(): void
    {
        $existing = DB::connection('tenant')->table('migrations')
            ->whereIn('migration', self::MIGRATIONS)
            ->pluck('migration')
            ->all();

        $missing = array_diff(self::MIGRATIONS, $existing);
        if (empty($missing)) {
            return;
        }

        $batch = (int) DB::connection('tenant')->table('migrations')->max('batch') + 1;
        foreach ($missing as $m) {
            DB::connection('tenant')->table('migrations')->insert([
                'migration' => $m,
                'batch'     => $batch,
            ]);
        }
    }
}
