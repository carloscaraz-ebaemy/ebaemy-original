<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Galería por variante: fotos ADICIONALES de cada variante (la principal
 * sigue en `item_variants.image`). Mismo modelo que `item_images` para el
 * producto: una fila por archivo, con `position` para el orden en la ficha.
 *
 * `item_variants.id` es bigint (se creó con `$table->id()` en 2026_03_22), así
 * que la FK es unsignedBigInteger. Al borrar la variante, sus fotos se van
 * con ella (los archivos los limpia ItemVariantController::destroy).
 *
 * Idempotente: los 17 tenants no se migran a la vez y `tenancy:migrate` a
 * veces no detecta archivos nuevos.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('item_variant_images')) return;
        if (!Schema::hasTable('item_variants')) return;

        Schema::create('item_variant_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_variant_id');
            $table->string('image', 255);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('item_variant_id', 'fk_variant_images_variant')
                ->references('id')->on('item_variants')
                ->onDelete('cascade');
            $table->index(['item_variant_id', 'position'], 'idx_variant_images_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variant_images');
    }
};
