<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valores posibles para cada opción.
 * Ej: opción "Color" → "Rojo", "Azul", "Negro"
 *     opción "Talla" → "S", "M", "L", "XL"
 *
 * El campo color_hex es opcional — solo para opciones de tipo color
 * para mostrar swatches en el ecommerce.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_option_values')) return;

        Schema::create('item_option_values', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('item_option_id');
            $table->foreign('item_option_id')
                  ->references('id')->on('item_options')
                  ->onDelete('cascade');

            $table->string('value', 100);              // "Rojo", "M", "Liso"
            $table->string('color_hex', 7)->nullable(); // "#FF0000" (solo si es color)
            $table->unsignedTinyInteger('position')->default(0);

            $table->timestamps();

            // Un valor no puede repetirse dentro de la misma opción
            $table->unique(['item_option_id', 'value'], 'uq_option_value');
            $table->index('item_option_id', 'idx_iov_option');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_option_values');
    }
};
