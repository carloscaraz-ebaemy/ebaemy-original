<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homologación de marcas ERP → Saga Falabella.
 *
 * Saga valida la marca del producto contra SU catálogo de marcas (GetBrands).
 * Si enviamos un nombre de marca que Saga no reconoce, rechaza la publicación.
 * Esta tabla mapea la marca interna (brands.id) → la marca exacta de Saga, igual
 * que saga_category_map hace con las categorías.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('saga_brand_map')) {
            Schema::create('saga_brand_map', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id');
                // FK a la tabla legacy `brands` (PK increments) → unsignedInteger.
                $table->unsignedInteger('brand_id');
                $table->string('saga_brand_id', 60)->nullable();
                $table->string('saga_brand_name', 191)->nullable();
                $table->timestamps();

                $table->foreign('channel_id')->references('id')->on('marketplace_channels')->onDelete('cascade');
                $table->unique(['channel_id', 'brand_id'], 'uniq_saga_brand_channel_brand');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saga_brand_map');
    }
};
