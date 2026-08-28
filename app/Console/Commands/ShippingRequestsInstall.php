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

    private const MIGRATION  = '2026_07_18_000001_create_shipping_requests_table';
    /** Migraciones posteriores que este comando también deja registradas. */
    private const MIGRATIONS_EXTRA = [
        '2026_07_20_000001_add_delivery_type_to_shipping_requests',
        '2026_07_20_000002_add_distance_and_settings_to_shipping',
        '2026_07_20_000003_add_delivery_pricing_to_shipping',
        '2026_07_21_000001_add_orders_whatsapp_to_shipping_settings',
        '2026_07_21_000002_add_agency_fee_to_shipping_settings',
        '2026_07_24_000001_add_payment_confirmation_to_shipping',
        '2026_07_24_000002_package_content_to_text',
        '2026_07_24_000003_add_document_type_to_shipping',
        '2026_07_25_000001_add_aging_settings_to_shipping_settings',
        '2026_07_26_000003_create_shipping_logistics_tables',
        '2026_08_02_000002_add_raffle_optin_to_shipping_requests',
        '2026_08_12_000001_add_agency_fee_mode_to_shipping_settings',
        '2026_08_13_000001_add_dispatch_to_shipping_requests',
        '2026_08_28_000001_add_payment_code_to_shipping_requests',
        '2026_08_28_000002_add_require_payment_code_to_shipping_settings',
    ];

    /**
     * Migración del rediseño logístico (lotes, impresiones, auditoría).
     * Es idempotente de por sí, así que en vez de duplicar aquí su schema se
     * ejecuta su up() directamente.
     */
    private const LOGISTICS_MIGRATION_FILE = '2026_07_26_000003_create_shipping_logistics_tables.php';

    /** Migraciones posteriores, idempotentes, que este comando tambien ejecuta. */
    private const EXTRA_MIGRATION_FILES = [
        '2026_08_02_000002_add_raffle_optin_to_shipping_requests.php',
    ];

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
                    if ($apply) {
                        $this->ensureSettingsTable();
                        $this->ensureLogisticsTables();
                    }
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
                    $table->string('delivery_type', 20)->default('agencia')->index();
                    $table->string('full_name', 160);
                    $table->string('dni', 15)->nullable();
                    $table->string('phone', 20)->nullable();
                    $table->string('shipping_destination', 255)->nullable();
                    $table->string('reference', 255)->nullable();
                    $table->string('destination_city', 120)->nullable();
                    $table->string('department_id', 2)->nullable();
                    $table->string('province_id', 4)->nullable();
                    $table->string('district_id', 6)->nullable();
                    // Google Maps (entregas a domicilio / motorizado)
                    $table->decimal('latitude', 10, 7)->nullable();
                    $table->decimal('longitude', 10, 7)->nullable();
                    $table->string('google_place_id', 255)->nullable();
                    $table->string('formatted_address', 500)->nullable();
                    $table->string('google_maps_url', 500)->nullable();
                    $table->decimal('distance_km', 6, 2)->nullable();
                    $table->string('distance_text', 40)->nullable();
                    $table->string('duration_text', 40)->nullable();
                    $table->decimal('delivery_price', 8, 2)->nullable();
                    $table->string('courier_name', 120)->nullable();
                    $table->string('courier_phone', 20)->nullable();
                    $table->string('shipping_agency', 120)->nullable();
                    $table->string('package_content', 255)->nullable();
                    $table->unsignedSmallInteger('package_count')->default(1);
                    $table->decimal('weight', 8, 2)->nullable();
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

                $this->ensureSettingsTable();
                $this->ensureLogisticsTables();
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
    private const NEW_COLUMNS = [
        'package_content', 'package_count', 'notes', 'department_id', 'province_id', 'district_id', 'weight', 'reference',
        // Rediseño tipo de entrega + Google Maps (2026-07-20)
        'delivery_type', 'latitude', 'longitude', 'google_place_id', 'formatted_address', 'google_maps_url', 'courier_name', 'courier_phone',
        // Distancia tienda→cliente (2026-07-20)
        'distance_km', 'distance_text', 'duration_text',
        // Confirmación de pago (2026-07-24)
        'payment_confirmed', 'payment_confirmed_at', 'payment_note',
        // Tipo de documento (2026-07-24)
        'document_type',
        // Precio del envío a domicilio (2026-07-20)
        'delivery_price',
        // Enlace con la Guía de Remisión (2026-08-13)
        'dispatch_id', 'dispatch_number', 'dispatch_generated_at',
        // Código de pago con control de duplicados (2026-08-28)
        'payment_code', 'payment_code_normalized',
    ];

    /** Crea/actualiza la tabla shipping_settings (origen + tarifas). */
    private function ensureSettingsTable(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_settings')) {
            Schema::connection('tenant')->create('shipping_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('store_latitude', 10, 7)->nullable();
                $table->decimal('store_longitude', 10, 7)->nullable();
                $table->string('store_address', 500)->nullable();
                $table->decimal('price_per_km', 8, 2)->nullable();
                $table->decimal('base_price', 8, 2)->nullable();
                $table->decimal('min_price', 8, 2)->nullable();
                $table->string('orders_whatsapp', 20)->nullable();
                $table->decimal('agency_fee', 8, 2)->nullable();
                $table->boolean('require_payment')->default(false);
                $table->boolean('require_payment_code')->default(false);
                $table->unsignedTinyInteger('max_business_days')->default(4);
                $table->boolean('aging_skip_holidays')->default(true);
                $table->timestamps();
            });
            return;
        }
        // Tabla existente: agregar las columnas nuevas si faltan.
        Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
            $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_settings', $c);
            if (!$has('price_per_km'))    $table->decimal('price_per_km', 8, 2)->nullable()->after('store_address');
            if (!$has('base_price'))      $table->decimal('base_price', 8, 2)->nullable()->after('price_per_km');
            if (!$has('min_price'))       $table->decimal('min_price', 8, 2)->nullable()->after('base_price');
            if (!$has('orders_whatsapp')) $table->string('orders_whatsapp', 20)->nullable()->after('min_price');
            if (!$has('agency_fee'))      $table->decimal('agency_fee', 8, 2)->nullable()->after('orders_whatsapp');
            if (!$has('require_payment')) $table->boolean('require_payment')->default(false)->after('agency_fee');
            // Semáforo de prioridad por días hábiles (2026-07-25).
            if (!$has('max_business_days'))   $table->unsignedTinyInteger('max_business_days')->default(4)->after('require_payment');
            if (!$has('aging_skip_holidays')) $table->boolean('aging_skip_holidays')->default(true)->after('max_business_days');
            // Modo del cobro tienda->agencia (2026-08-12): cobra / gratis / no mencionar.
            if (!$has('agency_fee_mode'))     $table->string('agency_fee_mode', 10)->default('hidden')->after('agency_fee');
            // Codigo de pago al confirmar, opcional por tienda (2026-08-28).
            if (!$has('require_payment_code')) $table->boolean('require_payment_code')->default(false)->after('require_payment');
        });

        // Quien ya cobraba sigue igual; nadie estrena "gratis" sin pedirlo.
        if (Schema::connection('tenant')->hasColumn('shipping_settings', 'agency_fee_mode')) {
            \Illuminate\Support\Facades\DB::connection('tenant')->table('shipping_settings')
                ->where('agency_fee', '>', 0)
                ->where('agency_fee_mode', 'hidden')
                ->update(['agency_fee_mode' => 'amount']);
        }
    }

    /**
     * Crea las tablas del rediseño logístico (lotes, impresiones, auditoría)
     * y las columnas que las acompañan, ejecutando la migración idempotente.
     */
    private function ensureLogisticsTables(): void
    {
        $path = database_path('migrations/tenant/' . self::LOGISTICS_MIGRATION_FILE);

        if (!is_file($path)) {
            return;
        }

        // La migración usa el facade Schema sin conexión explícita (como el
        // resto de migraciones de tenant). Al correrla desde este comando la
        // conexión por defecto puede no ser la del tenant, así que se fija
        // durante la ejecución y se restaura después.
        $previous = config('database.default');
        config(['database.default' => 'tenant']);

        try {
            $migration = require $path;
            $migration->up();

            foreach (self::EXTRA_MIGRATION_FILES as $extra) {
                $p = database_path('migrations/tenant/' . $extra);
                if (is_file($p)) { (require $p)->up(); }
            }
        } finally {
            config(['database.default' => $previous]);
        }
    }

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
            if (in_array('weight', $missing, true)) {
                $table->decimal('weight', 8, 2)->nullable()->after('package_count');
            }
            if (in_array('reference', $missing, true)) {
                $table->string('reference', 255)->nullable()->after('shipping_destination');
            }
            // ── Rediseño tipo de entrega + Google Maps ──
            if (in_array('delivery_type', $missing, true)) {
                $table->string('delivery_type', 20)->default('agencia')->after('order_id')->index();
            }
            if (in_array('latitude', $missing, true)) {
                $table->decimal('latitude', 10, 7)->nullable()->after('district_id');
            }
            if (in_array('longitude', $missing, true)) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (in_array('google_place_id', $missing, true)) {
                $table->string('google_place_id', 255)->nullable()->after('longitude');
            }
            if (in_array('formatted_address', $missing, true)) {
                $table->string('formatted_address', 500)->nullable()->after('google_place_id');
            }
            if (in_array('google_maps_url', $missing, true)) {
                $table->string('google_maps_url', 500)->nullable()->after('formatted_address');
            }
            if (in_array('courier_name', $missing, true)) {
                $table->string('courier_name', 120)->nullable()->after('google_maps_url');
            }
            if (in_array('courier_phone', $missing, true)) {
                $table->string('courier_phone', 20)->nullable()->after('courier_name');
            }
            if (in_array('distance_km', $missing, true)) {
                $table->decimal('distance_km', 6, 2)->nullable()->after('google_maps_url');
            }
            if (in_array('distance_text', $missing, true)) {
                $table->string('distance_text', 40)->nullable()->after('distance_km');
            }
            if (in_array('duration_text', $missing, true)) {
                $table->string('duration_text', 40)->nullable()->after('distance_text');
            }
            // Enlace con la Guia de Remision (2026-08-13).
            if (in_array('dispatch_id', $missing, true)) {
                $table->unsignedInteger('dispatch_id')->nullable()->index();
            }
            if (in_array('dispatch_number', $missing, true)) {
                $table->string('dispatch_number', 30)->nullable();
            }
            if (in_array('dispatch_generated_at', $missing, true)) {
                $table->dateTime('dispatch_generated_at')->nullable();
            }
            if (in_array('delivery_price', $missing, true)) {
                $table->decimal('delivery_price', 8, 2)->nullable()->after('duration_text');
            }
            if (in_array('payment_confirmed', $missing, true)) {
                $table->boolean('payment_confirmed')->default(false)->after('delivery_price');
            }
            if (in_array('payment_confirmed_at', $missing, true)) {
                $table->timestamp('payment_confirmed_at')->nullable()->after('payment_confirmed');
            }
            if (in_array('payment_note', $missing, true)) {
                $table->string('payment_note', 255)->nullable()->after('payment_confirmed_at');
            }
            if (in_array('document_type', $missing, true)) {
                $table->string('document_type', 12)->nullable()->after('dni');
            }
            if (in_array('payment_code', $missing, true)) {
                $table->string('payment_code', 60)->nullable()->after('payment_confirmed_at');
            }
            if (in_array('payment_code_normalized', $missing, true)) {
                $table->string('payment_code_normalized', 60)->nullable()->index();
            }
        });

        return $missing;
    }

    private function registerMigrationIfMissing(): void
    {
        $batch = (int) DB::connection('tenant')->table('migrations')->max('batch') + 1;

        foreach (array_merge([self::MIGRATION], self::MIGRATIONS_EXTRA) as $mig) {
            $exists = DB::connection('tenant')->table('migrations')
                ->where('migration', $mig)->exists();
            if (!$exists) {
                DB::connection('tenant')->table('migrations')->insert([
                    'migration' => $mig,
                    'batch'     => $batch,
                ]);
            }
        }
    }
}
