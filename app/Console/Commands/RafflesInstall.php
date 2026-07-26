<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea las tablas del módulo de Sorteos (raffles, raffle_participants,
 * raffle_winners) en TODOS los tenants.
 *
 * Mismo motivo que ShippingRequestsInstall / PricingPhase1Install:
 * `tenancy:migrate --force` no siempre detecta los archivos nuevos en
 * producción (cache del descubridor de hyn). Este comando aplica el schema
 * idempotentemente y registra la migración en la tabla `migrations` de cada
 * tenant para que no se re-corra.
 *
 * Idempotente y re-ejecutable. Uso:
 *   php artisan raffles:install            # dry-run (solo reporta)
 *   php artisan raffles:install --apply    # aplica
 */
class RafflesInstall extends Command
{
    protected $signature = 'raffles:install {--apply : Ejecutar la creación (sin esto solo reporta estado)}';
    protected $description = 'Crea las tablas del módulo de Sorteos en todos los tenants';

    private const MIGRATION = '2026_07_26_000001_create_raffles_tables';

    private const TABLES = ['raffles', 'raffle_participants', 'raffle_winners'];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Modo APPLY — se crearán las tablas donde falten.' : 'Modo DRY-RUN — solo reporta estado actual.');
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

                $missing = array_values(array_filter(
                    self::TABLES,
                    fn ($t) => !Schema::connection('tenant')->hasTable($t)
                ));

                if (empty($missing)) {
                    $this->line("  <fg=gray>=</> {$hn->fqdn}: ya existe");
                    if ($apply) {
                        $this->registerMigrationIfMissing();
                    }
                    $summary['ok']++;
                    continue;
                }

                if (!$apply) {
                    $this->line("  <fg=yellow>~</> {$hn->fqdn}: faltan " . implode(', ', $missing));
                    continue;
                }

                $this->createTables($missing);
                $this->registerMigrationIfMissing();

                $this->line("  <fg=green>+</> {$hn->fqdn}: CREADAS (" . implode(', ', $missing) . ")");
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

    private function createTables(array $missing): void
    {
        $schema = Schema::connection('tenant');

        if (in_array('raffles', $missing, true)) {
            $schema->create('raffles', function (Blueprint $table) {
                $table->id();
                $table->string('code', 20)->unique();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->text('terms')->nullable();

                $table->string('prize_name', 160)->nullable();
                $table->text('prize_description')->nullable();
                $table->string('prize_image', 255)->nullable();
                $table->json('prize_gallery')->nullable();
                $table->unsignedSmallInteger('prize_quantity')->default(1);
                $table->decimal('prize_value', 10, 2)->nullable();

                $table->string('status', 20)->default('draft')->index();
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('registration_closes_at')->nullable();
                $table->dateTime('draw_at')->nullable();
                $table->dateTime('winner_published_at')->nullable();

                $table->json('sources')->nullable();
                $table->boolean('require_paid')->default(true);
                $table->date('purchase_from')->nullable();
                $table->date('purchase_to')->nullable();
                $table->decimal('min_amount', 12, 2)->nullable();
                $table->unsignedInteger('establishment_id')->nullable();
                $table->unsignedInteger('channel_id')->nullable();
                $table->json('category_ids')->nullable();
                $table->json('item_ids')->nullable();

                $table->unsignedInteger('created_by')->nullable();
                $table->timestamps();
                $table->index('draw_at');
            });
        }

        if (in_array('raffle_participants', $missing, true)) {
            $schema->create('raffle_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('raffle_id');
                $table->unsignedInteger('person_id')->nullable();

                $table->string('full_name', 200);
                $table->string('document', 20)->nullable();
                $table->string('email', 160)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('dedupe_key', 190);

                $table->string('token', 40)->unique();
                $table->string('status', 20)->default('invited');

                $table->unsignedSmallInteger('orders_count')->default(0);
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->date('last_purchase_at')->nullable();

                $table->dateTime('invited_at')->nullable();
                $table->string('invited_via', 20)->nullable();

                $table->dateTime('accepted_at')->nullable();
                $table->string('accept_ip', 45)->nullable();
                $table->string('accept_user_agent', 255)->nullable();

                $table->boolean('is_winner')->default(false);
                $table->timestamps();

                $table->unique(['raffle_id', 'dedupe_key'], 'raffle_participants_unique_person');
                $table->index(['raffle_id', 'status']);
            });
        }

        if (in_array('raffle_winners', $missing, true)) {
            $schema->create('raffle_winners', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('raffle_id');
                $table->unsignedBigInteger('participant_id');
                $table->unsignedSmallInteger('position')->default(1);

                $table->string('prize_name', 160)->nullable();
                $table->string('prize_image', 255)->nullable();

                $table->dateTime('drawn_at')->nullable();
                $table->unsignedInteger('drawn_by')->nullable();
                $table->string('drawn_by_name', 120)->nullable();
                $table->json('draw_snapshot')->nullable();

                $table->string('delivery_status', 20)->default('pending');
                $table->dateTime('delivered_at')->nullable();
                $table->string('delivery_note', 255)->nullable();

                $table->timestamps();
                $table->index(['raffle_id', 'position']);
                $table->index('participant_id');
            });
        }
    }

    /** Deja la migración marcada como corrida para que tenancy:migrate no la repita. */
    private function registerMigrationIfMissing(): void
    {
        if (!Schema::connection('tenant')->hasTable('migrations')) {
            return;
        }

        $exists = DB::connection('tenant')->table('migrations')
                    ->where('migration', self::MIGRATION)->exists();

        if ($exists) {
            return;
        }

        $batch = (int) DB::connection('tenant')->table('migrations')->max('batch');

        DB::connection('tenant')->table('migrations')->insert([
            'migration' => self::MIGRATION,
            'batch'     => $batch > 0 ? $batch : 1,
        ]);
    }
}
