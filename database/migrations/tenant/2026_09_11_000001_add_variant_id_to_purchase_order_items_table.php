<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega variant_id a purchase_order_items.
 *
 * La orden de compra no sabía QUÉ variante se compraba. Al recibirla,
 * applyReceptionStock() acreditaba todas las unidades a la variante primaria:
 * recibir 10 pares de talla 40 sumaba 10 a la talla 38. El total del producto
 * quedaba bien y el stock por talla quedaba mal — que es justo el que se usa
 * para vender.
 *
 * nullable → retrocompatible: las OC históricas mantienen variant_id = null y
 * se reciben exactamente como hasta ahora (fallback a la variante primaria).
 *
 * Mismo patrón que 2026_03_22_000007 para logistic_order_items, incluidos los
 * try/catch alrededor de la FK y del índice: hay tenants cuyo motor o cuyo
 * estado de migraciones rechaza una de las dos, y la columna —que es lo que
 * de verdad hace falta— debe quedar creada igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_order_items')) return;
        if (Schema::hasColumn('purchase_order_items', 'variant_id')) return;

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_id')
                  ->nullable()
                  ->after('item_id')
                  ->comment('FK a item_variants; null = producto sin variante u OC anterior a esta columna');
        });

        try {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->foreign('variant_id')
                      ->references('id')
                      ->on('item_variants')
                      ->onDelete('set null');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->index('variant_id', 'idx_poi_variant');
            });
        } catch (\Exception $e) {}
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_order_items')) return;
        if (!Schema::hasColumn('purchase_order_items', 'variant_id')) return;

        try {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropForeign(['variant_id']);
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropIndex('idx_poi_variant');
            });
        } catch (\Exception $e) {}

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('variant_id');
        });
    }
};
