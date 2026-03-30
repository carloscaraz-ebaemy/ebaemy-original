<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Variantes generadas a partir de las combinaciones de opciones.
 * Ej: "Rojo / M", "Azul / L", "Negro / S"
 *
 * Campos de precio y barcode son NULLABLE — si null, hereda del producto padre.
 * variant_hash: MD5 de los option_value_ids ordenados → evita duplicados.
 * is_active: permite desactivar una combinación sin borrarla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('item_id');
            $table->foreign('item_id')->references('id')->on('items')->onDelete('cascade');

            // SKU y código de barras propios (nullable → hereda del padre)
            $table->string('sku', 100)->nullable();
            $table->string('barcode', 100)->nullable();

            // Precio propio (nullable → hereda sale_unit_price del item padre)
            $table->decimal('sale_unit_price', 12, 4)->nullable();

            // Costo propio (nullable → hereda purchase_unit_price del item padre)
            $table->decimal('purchase_unit_price', 12, 4)->nullable();

            // Imagen específica de esta variante (nullable → usa imagen del producto)
            $table->string('image', 255)->nullable();

            // Hash MD5 de los option_value_ids ordenados — detecta duplicados
            $table->string('variant_hash', 32);

            // Nombre legible generado automáticamente: "Rojo / M / Liso"
            $table->string('display_name', 255)->nullable();

            $table->boolean('is_active')->default(true);

            // Stock agregado por compatibilidad (se actualiza desde item_variant_warehouse)
            $table->decimal('stock', 12, 4)->default(0);

            $table->timestamps();

            // Un hash de variante es único por producto
            $table->unique(['item_id', 'variant_hash'], 'uq_item_variant_hash');

            $table->index('item_id', 'idx_variants_item');
            $table->index('is_active', 'idx_variants_active');
            $table->index('sku', 'idx_variants_sku');
            $table->index('barcode', 'idx_variants_barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variants');
    }
};
