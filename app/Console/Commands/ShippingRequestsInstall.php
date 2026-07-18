<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea la tabla `shipping_requests` (módulo Registro y Control de Envíos)
 * en TODOS los tenants.
 *
 * Existe por el mismo motivo que PricingPhase1Install: `tenancy:migrate --force`
 * no siempre detecta los archivos nuevos en producción (cache del descubridor
 * de hyn). Este comando aplica el schema idempotentemente y registra la
 * migración en la tabla `migrations` de cada tenant para que no se re-corra.
 *
 * Idempotente y re-ejecutable. Uso:
 *   php artisan shipping:install            # dry-run (solo reporta)
 *   php artisan shipping:install --apply    # aplica
 */
class ShippingRequestsInstall extends Command
{
    protected $signature = 'shipping:install {--apply : Ejecutar la creación (sin esto solo reporta estado)}';
    protected $description = 'Crea shipping_requests en todos los tenants (módulo Registro y Control de Envíos)';

    private const MIGRATION = '2026_07_18_000001_create_shipping_requests_table';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Modo APPLY — se creará la tabla donde falte.' : 'Modo DRY-RUN — solo reporta estado actual.');
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

                if (Schema::connection('tenant')->hasTable('shipping_requests')) {
                    $added = $apply ? $this->ensureColumns() : $this->missingColumns();
                    $this->line("  <fg=gray>=</> {$hn->fqdn}: ya existe" . (!empty($added) ? " (columnas: " . implode(', ', $added) . ")" : ""));
                    $this->registerMigrationIfMissing();
                    $summary['ok']++;
                    continue;
                }

                if (!$apply) {
                    $this->line("  <fg=yellow>~</> {$hn->fqdn}: se CREARÍA la tabla");
                    continue;
                }

                Schema::connection('tenant')->create('shipping_requests', function (Blueprint $table) {
                    $table->id();
                    $table->string('shipment_code', 20)->nullable()->unique();
                    $table->unsignedInteger('order_id')->nullable();
                    $table->string('full_name', 160);
                    $table->string('dni', 15)->nullable();
                    $table->string('phone', 20)->nullable();
                    $table->string('shipping_destination', 255)->nullable();
                    $table->string('destination_city', 120)->nullable();
                    $table->string('department_id', 2)->nullable();
                    $table->string('province_id', 4)->nullable();
                    $table->string('district_id', 6)->nullable();
                    $table->string('shipping_agency', 120)->nullable();
                    $table->string('package_content', 255)->nullable();
                    $table->unsignedSmallInteger('package_count')->default(1);
                    $table->string('notes', 255)->nullable();
                    $table->string('tracking_number', 120)->nullable();
                    $table->string('shipping_guide_path', 255)->nullable();
                    $table->string('observation', 255)->nullable();
                    $table->string('status', 20)->default('pendiente');
                    $table->boolean('accepted_terms')->default(false);
                    $table->timestamp('sent_at')->nullable();
                    $table->unsignedInteger('created_by')->nullable();
                    $table->timestamps();
                    $table->index('status');
                    $table->index('order_id');
                    $table->index('created_at');
                });

                $this->registerMigrationIfMissing();
                $this->line("  <fg=green>+</> {$hn->fqdn}: CREADA");
                $summary['applied']++;
            } catch (\Throwable $e) {
                $summary['errors']++;
                $this->error("  {$hn->fqdn}: ERROR — " . $e->getMessage());
            }
        }

        $tenancy->tenant($originalT ?: null);

        $this->line('');
        $this->info("Resumen: ya-OK={$summary['ok']} · creadas={$summary['applied']} · errores={$summary['errors']}");

        if (!$apply) {
            $this->line('');
            $this->comment('Ejecuta con --apply para crear las tablas.');
        }

        return $summary['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** Columnas añadidas después del create original (para tenants ya creados). */
    private const NEW_COLUMNS = ['package_content', 'package_count', 'notes', 'department_id', 'province_id', 'district_id'];

    /** Devuelve las columnas nuevas que aún faltan en la tabla. */
    private function missingColumns(): array
    {
        return array_values(array_filter(self::NEW_COLUMNS, function ($col) {
            return !Schema::connection('tenant')->hasColumn('shipping_requests', $col);
        }));
    }

    /** Agrega idempotentemente las columnas nuevas que falten. Devuelve las añadidas. */
    private function ensureColumns(): array
    {
        $missing = $this->missingColumns();
        if (empty($missing)) {
            return [];
        }

        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) use ($missing) {
            if (in_array('package_content', $missing, true)) {
                $table->string('package_content', 255)->nullable()->after('shipping_agency');
            }
            if (in_array('package_count', $missing, true)) {
                $table->unsignedSmallInteger('package_count')->default(1)->after('package_content');
            }
            if (in_array('notes', $missing, true)) {
                $table->string('notes', 255)->nullable()->after('package_count');
            }
            if (in_array('department_id', $missing, true)) {
                $table->string('department_id', 2)->nullable()->after('destination_city');
            }
            if (in_array('province_id', $missing, true)) {
                $table->string('province_id', 4)->nullable()->after('department_id');
            }
            if (in_array('district_id', $missing, true)) {
                $table->string('district_id', 6)->nullable()->after('province_id');
            }
        });

        return $missing;
    }

    private function registerMigrationIfMissing(): void
    {
        $exists = DB::connection('tenant')->table('migrations')
            ->where('migration', self::MIGRATION)
            ->exists();

        if ($exists) {
            return;
        }

        $batch = (int) DB::connection('tenant')->table('migrations')->max('batch') + 1;
        DB::connection('tenant')->table('migrations')->insert([
            'migration' => self::MIGRATION,
            'batch'     => $batch,
        ]);
    }
}
