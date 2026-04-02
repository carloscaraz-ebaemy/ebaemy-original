<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opciones de variante por producto.
 * Ej: item_id=5 → "Color", "Talla", "Material"
 *
 * Una opción pertenece a un solo producto.
 * El orden permite mostrarlas en el mismo orden en que el usuario las creó.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_options')) return;

        Schema::create('item_options', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('item_id');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');

            $table->string('name', 80);           // "Color", "Talla", "Material"
            $table->unsignedTinyInteger('position')->default(0); // orden en UI

            $table->timestamps();

            // Un producto no puede tener dos opciones con el mismo nombre
            $table->unique(['item_id', 'name'], 'uq_item_option_name');
            $table->index('item_id', 'idx_item_options_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_options');
    }
};
