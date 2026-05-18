<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 rediseño precios: configuracion por tenant.
 *
 * Una sola fila por tenant (es un schema multi-tenant DB-per-tenant).
 * Permite ajustar guardrails sin tocar codigo:
 *  - default_min_margin_pct: margen minimo default para items sin override
 *  - block_sales_below_cost: si false, solo advierte (no recomendado)
 *  - category_min_margins: JSON {category_id: min_pct} para reglas por categoria
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('pricing_settings')) {
            Schema::create('pricing_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('default_min_margin_pct', 5, 2)->default(10)
                    ->comment('Margen minimo default si el item no tiene min_margin_pct propio');
                $table->boolean('block_sales_below_cost')->default(true)
                    ->comment('Si true, ItemRequest rechaza sale_price < cost (salvo liquidation_mode item)');
                $table->boolean('audit_cost_changes')->default(true)
                    ->comment('Si true, hook saving registra cambios de costo en item_price_history');
                $table->json('category_min_margins')->nullable()
                    ->comment('Override min_margin_pct por categoria: {category_id: pct}');
                $table->timestamps();
            });

            // Insertar fila default unica
            DB::table('pricing_settings')->insert([
                'default_min_margin_pct' => 10,
                'block_sales_below_cost' => true,
                'audit_cost_changes'     => true,
                'category_min_margins'   => null,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_settings');
    }
};
