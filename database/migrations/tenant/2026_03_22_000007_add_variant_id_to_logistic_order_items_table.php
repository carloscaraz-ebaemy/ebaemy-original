<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega variant_id a logistic_order_items.
 *
 * nullable → retrocompatible: los pedidos existentes sin variante
 * mantienen variant_id = null, lo que significa "producto padre".
 *
 * Con este campo se puede:
 *   - Saber qué variante específica pidió el cliente
 *   - Reservar stock del almacén correcto (item_variant_warehouse)
 *   - Mostrar "Color: Rojo / Talla: M" en el picking
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistic_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('variant_id')
                  ->nullable()
                  ->after('item_id')
                  ->comment('FK a item_variants; null = producto sin variante');

            $table->foreign('variant_id')
                  ->references('id')
                  ->on('item_variants')
                  ->onDelete('set null');

            $table->index('variant_id', 'idx_loi_variant');
        });
    }

    public function down(): void
    {
        Schema::table('logistic_order_items', function (Blueprint $table) {
            $table->dropForeign('logistic_order_items_variant_id_foreign');
            $table->dropIndex('idx_loi_variant');
            $table->dropColumn('variant_id');
        });
    }
};
