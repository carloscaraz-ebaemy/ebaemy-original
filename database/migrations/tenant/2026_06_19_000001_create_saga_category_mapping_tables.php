<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homologación de categorías ERP → Saga Falabella (Fase 1).
 *
 * Saga NO acepta el nombre de la categoría interna como PrimaryCategory: exige
 * el ID de su propio árbol (GetCategoryTree) y, según la categoría, distintos
 * atributos obligatorios (GetCategoryAttributes). Estas tablas guardan:
 *   - saga_category_map: categoría interna (categories.id) → categoría Saga.
 *   - saga_category_attributes: caché de los atributos por categoría Saga,
 *     para que el builder del payload (Fase 2) sepa qué es obligatorio sin
 *     volver a llamar a la API en cada push.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('saga_category_map')) {
            Schema::create('saga_category_map', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id');
                // FK a la tabla legacy `categories` (PK increments) → unsignedInteger.
                $table->unsignedInteger('category_id');
                $table->string('saga_category_id', 60);
                $table->string('saga_category_name', 191)->nullable();
                $table->string('saga_category_path', 500)->nullable(); // breadcrumb para mostrar
                $table->timestamps();

                $table->foreign('channel_id')->references('id')->on('marketplace_channels')->onDelete('cascade');
                $table->unique(['channel_id', 'category_id'], 'uniq_saga_cat_channel_category');
                $table->index('saga_category_id');
            });
        }

        if (!Schema::hasTable('saga_category_attributes')) {
            Schema::create('saga_category_attributes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('channel_id');
                $table->string('saga_category_id', 60);
                $table->json('attributes')->nullable(); // payload normalizado de GetCategoryAttributes
                $table->timestamp('fetched_at')->nullable();
                $table->timestamps();

                $table->foreign('channel_id')->references('id')->on('marketplace_channels')->onDelete('cascade');
                $table->unique(['channel_id', 'saga_category_id'], 'uniq_saga_attr_channel_category');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('saga_category_attributes');
        Schema::dropIfExists('saga_category_map');
    }
};
