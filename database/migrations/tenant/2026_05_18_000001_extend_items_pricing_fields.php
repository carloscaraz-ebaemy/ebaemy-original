<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 rediseño precios: extiende items con campos de margen profesional.
 *
 * - target_margin_pct: reemplaza al huérfano percentage_of_profit (se migran datos).
 * - min_margin_pct + floor_price: guardrails para evitar venta bajo costo.
 * - compare_at_price: precio tachado tipo Shopify/MercadoLibre.
 * - landed_cost_extra_pct: % adicional al costo (flete, importación).
 * - liquidation_mode: flag explícito producto-por-producto para permitir venta bajo costo.
 *
 * Fórmula canónica: margin sobre venta (no markup).
 *   list_price  = effective_cost / (1 - target_margin_pct/100)
 *   floor_price = effective_cost / (1 - min_margin_pct/100)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'landed_cost_extra_pct')) {
                $table->decimal('landed_cost_extra_pct', 5, 2)->default(0)->after('purchase_unit_price')
                    ->comment('% adicional al costo (flete, importacion, mermas)');
            }
            if (!Schema::hasColumn('items', 'target_margin_pct')) {
                $table->decimal('target_margin_pct', 5, 2)->nullable()->after('landed_cost_extra_pct')
                    ->comment('Margen objetivo sobre venta (sustituye percentage_of_profit)');
            }
            if (!Schema::hasColumn('items', 'min_margin_pct')) {
                $table->decimal('min_margin_pct', 5, 2)->nullable()->after('target_margin_pct')
                    ->comment('Margen minimo permitido (guardrail descuentos)');
            }
            if (!Schema::hasColumn('items', 'compare_at_price')) {
                $table->decimal('compare_at_price', 12, 4)->nullable()->after('sale_unit_price')
                    ->comment('Precio tachado mostrado al cliente (referencia visual)');
            }
            if (!Schema::hasColumn('items', 'floor_price')) {
                $table->decimal('floor_price', 12, 4)->nullable()->after('compare_at_price')
                    ->comment('Precio piso calculado desde min_margin_pct');
            }
            if (!Schema::hasColumn('items', 'pricing_mode')) {
                $table->enum('pricing_mode', ['margin', 'markup', 'manual'])->default('margin')->after('floor_price')
                    ->comment('Modo de calculo: margin (sobre venta), markup (sobre costo), manual');
            }
            if (!Schema::hasColumn('items', 'liquidation_mode')) {
                $table->boolean('liquidation_mode')->default(false)->after('pricing_mode')
                    ->comment('Si true permite vender bajo costo (modo liquidacion explicito por producto)');
            }
            if (!Schema::hasColumn('items', 'floor_price_recalc_at')) {
                $table->timestamp('floor_price_recalc_at')->nullable()->after('liquidation_mode')
                    ->comment('Ultimo recalculo del floor_price (job nocturno)');
            }
        });

        // Migrar datos del campo legacy percentage_of_profit al nuevo target_margin_pct
        // (percentage_of_profit queda como deprecated, se elimina en Fase 4)
        if (Schema::hasColumn('items', 'percentage_of_profit') && Schema::hasColumn('items', 'target_margin_pct')) {
            DB::statement('UPDATE items SET target_margin_pct = percentage_of_profit WHERE target_margin_pct IS NULL AND percentage_of_profit IS NOT NULL AND percentage_of_profit > 0');
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'landed_cost_extra_pct',
                'target_margin_pct',
                'min_margin_pct',
                'compare_at_price',
                'floor_price',
                'pricing_mode',
                'liquidation_mode',
                'floor_price_recalc_at',
            ]);
        });
    }
};
