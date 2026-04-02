<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivote: qué valores de opción componen cada variante.
 *
 * Ej: variante "Rojo / M" tiene dos filas:
 *   (variant_id=1, option_value_id=3)  ← "Rojo"
 *   (variant_id=1, option_value_id=7)  ← "M"
 *
 * Con esta estructura se puede reconstruir la variante a partir de
 * cualquier combinación de valores, y también hacer la consulta inversa:
 * "¿qué variante corresponde a Color=Rojo y Talla=M?"
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_variant_value_map')) return;

        Schema::create('item_variant_value_map', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('item_variant_id');
            $table->foreign('item_variant_id')
                  ->references('id')->on('item_variants')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('item_option_value_id');
            $table->foreign('item_option_value_id')
                  ->references('id')->on('item_option_values')
                  ->onDelete('cascade');

            // Una variante solo puede tener un valor por opción
            $table->unique(
                ['item_variant_id', 'item_option_value_id'],
                'uq_variant_value'
            );

            $table->index('item_variant_id',       'idx_vvm_variant');
            $table->index('item_option_value_id',  'idx_vvm_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variant_value_map');
    }
};
