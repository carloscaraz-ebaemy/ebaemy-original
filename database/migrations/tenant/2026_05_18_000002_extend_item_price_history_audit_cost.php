<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 rediseño precios: extiende item_price_history para auditar tambien
 * cambios de COSTO (purchase_unit_price), no solo de precio de venta.
 *
 * Hasta hoy solo trazabamos cambios de sale_unit_price — el costo cambiaba
 * silenciosamente y eros​ionaba el margen sin alertas.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('item_price_history', function (Blueprint $table) {
            if (!Schema::hasColumn('item_price_history', 'old_cost')) {
                $table->decimal('old_cost', 12, 4)->nullable()->after('new_price')
                    ->comment('Costo anterior (purchase_unit_price)');
            }
            if (!Schema::hasColumn('item_price_history', 'new_cost')) {
                $table->decimal('new_cost', 12, 4)->nullable()->after('old_cost')
                    ->comment('Costo nuevo (purchase_unit_price)');
            }
            if (!Schema::hasColumn('item_price_history', 'margin_at_change')) {
                $table->decimal('margin_at_change', 5, 2)->nullable()->after('new_cost')
                    ->comment('Margen efectivo calculado al momento del cambio');
            }
            if (!Schema::hasColumn('item_price_history', 'change_type')) {
                $table->enum('change_type', ['price', 'cost', 'both'])->default('price')->after('margin_at_change')
                    ->comment('Que cambio: precio venta, costo, o ambos');
            }
        });

        // Hacer old_price y new_price nullable para soportar registros que solo
        // documentan cambio de costo (no de precio)
        Schema::table('item_price_history', function (Blueprint $table) {
            $table->decimal('old_price', 12, 2)->nullable()->change();
            $table->decimal('new_price', 12, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('item_price_history', function (Blueprint $table) {
            $table->dropColumn(['old_cost', 'new_cost', 'margin_at_change', 'change_type']);
        });
    }
};
