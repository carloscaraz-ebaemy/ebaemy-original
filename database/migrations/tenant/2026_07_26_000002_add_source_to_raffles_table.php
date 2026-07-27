<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Origen de participantes enchufable.
 *
 * Antes el sorteo tenía los criterios cableados en columnas (require_paid,
 * purchase_from, establishment_id…). Ahora guarda QUÉ origen usa (`source`) y
 * los filtros propios de ese origen (`source_filters`, JSON), de modo que
 * sumar un módulo nuevo no exige tocar el esquema.
 *
 * Las columnas viejas se conservan: los sorteos creados antes se migran a
 * `source_filters` en el up() y siguen abriéndose sin perder criterios.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('raffles')) {
            return;
        }

        Schema::table('raffles', function (Blueprint $table) {
            if (!Schema::hasColumn('raffles', 'source')) {
                $table->string('source', 40)->default('orders_management')->after('status')->index();
            }
            if (!Schema::hasColumn('raffles', 'source_filters')) {
                $table->json('source_filters')->nullable()->after('source');
            }
            if (!Schema::hasColumn('raffles', 'participants_confirmed_at')) {
                $table->dateTime('participants_confirmed_at')->nullable()->after('source_filters');
            }
        });

        $this->migrateLegacyCriteria();
    }

    /** Traslada los criterios de las columnas antiguas al JSON de filtros. */
    private function migrateLegacyCriteria(): void
    {
        if (!Schema::hasColumn('raffles', 'require_paid')) {
            return;
        }

        \App\Models\Tenant\Raffle::whereNull('source_filters')->get()->each(function ($raffle) {
            $raffle->source         = 'orders_management';
            $raffle->source_filters = array_filter([
                'paid'             => (bool) ($raffle->require_paid ?? true),
                'date_from'        => $raffle->purchase_from?->format('Y-m-d'),
                'date_to'          => $raffle->purchase_to?->format('Y-m-d'),
                'establishment_id' => $raffle->establishment_id,
                'categories'       => $raffle->category_ids ?: null,
                'items'            => $raffle->item_ids ?: null,
            ], fn ($v) => $v !== null && $v !== []);

            $raffle->saveQuietly();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('raffles')) {
            return;
        }

        Schema::table('raffles', function (Blueprint $table) {
            foreach (['source', 'source_filters', 'participants_confirmed_at'] as $column) {
                if (Schema::hasColumn('raffles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
