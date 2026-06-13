<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 4 rediseño precios: snapshot de items con margen erosionado.
 *
 * El job nocturno `pricing:monitor-floor` (FloorPriceMonitor) recalcula el
 * floor_price de todos los items y registra aquí los que están en riesgo:
 *  - severity = 'loss'        → sale_price < effective_cost (vende con pérdida)
 *  - severity = 'below_floor' → sale_price < floor_price (rompe margen mínimo)
 *
 * La tabla se reemplaza por completo en cada corrida (refleja el estado actual,
 * no es histórico). El reporte admin lee de aquí sin recalcular.
 *
 * FK a items legacy con unsignedInteger (convención multi-tenant del proyecto).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pricing_margin_alerts')) {
            return;
        }

        Schema::create('pricing_margin_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_id')->index();
            $table->string('item_description')->nullable()
                ->comment('Snapshot del nombre para listar sin join');
            $table->unsignedInteger('category_id')->nullable()->index();
            $table->string('category_name')->nullable();

            $table->enum('severity', ['loss', 'below_floor'])->index()
                ->comment('loss = bajo costo (pérdida) · below_floor = bajo margen mínimo');

            $table->decimal('effective_cost', 12, 4)->default(0);
            $table->decimal('sale_price', 12, 4)->default(0);
            $table->decimal('floor_price', 12, 4)->nullable();
            $table->decimal('compare_at_price', 12, 4)->nullable();
            $table->decimal('margin_pct', 6, 2)->nullable()
                ->comment('Margen real al momento del escaneo (puede ser negativo)');
            $table->decimal('applied_min_margin_pct', 5, 2)->nullable()
                ->comment('Min margin aplicado: item > categoría > default');
            $table->decimal('loss_per_unit', 12, 4)->default(0)
                ->comment('Pérdida o déficit vs floor por unidad (siempre >= 0)');

            $table->boolean('liquidation_mode')->default(false)
                ->comment('Si true, el seller marcó liquidación explícita (informativo)');
            $table->boolean('apply_store')->default(false);
            $table->boolean('marketplace_publishable')->default(false);

            $table->timestamp('detected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_margin_alerts');
    }
};
