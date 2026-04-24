<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Árbol oficial de categorías del marketplace ebaemy.
 *
 * Diferente de `categories` (per-tenant), que cada tienda usa para organizar
 * SU catálogo interno. Esta tabla vive en el schema system y la administra
 * SOLO el SuperAdmin desde /admin/marketplace/categories.
 *
 * El seller NO crea categorías aquí — selecciona una existente al publicar
 * su producto. Si necesita una nueva, abre una marketplace_category_request.
 *
 * Estructura jerárquica self-referencing con 2 ayudas de performance:
 *   - level: profundidad numérica (0 = raíz)
 *   - depth_path: ruta denormalizada con IDs (ej "/1/4/15") para queries
 *     "todos los descendientes" sin recursividad SQL
 *   - full_slug: slug completo separado por "/" (ej "hogar/decoracion/plantas")
 *     usado para URLs SEO inmutables
 */
class CreateMarketplaceCategoriesTable extends Migration
{
    public function up()
    {
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Jerarquía
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedTinyInteger('level')->default(0)
                  ->comment('0 = raíz, 1 = subcategoría, 2 = tipo de producto, etc.');
            $table->string('depth_path', 500)->nullable()->index()
                  ->comment('Path denormalizado de ancestros con IDs, ej "/1/4/15", para queries de descendientes');

            // Identificación
            $table->string('name', 120);
            $table->string('slug', 80)
                  ->comment('Slug local (único bajo el mismo parent_id)');
            $table->string('full_slug', 500)
                  ->comment('Slug completo separado por "/", ej "hogar/decoracion/plantas-artificiales" — usado en URLs SEO');

            // Visual
            $table->string('icon', 80)->nullable()
                  ->comment('Nombre de icono (lucide/tabler) o emoji');
            $table->string('image', 500)->nullable()
                  ->comment('Path relativo en storage o URL absoluta');
            $table->text('description')->nullable();

            // Flags de visibilidad
            $table->boolean('is_active')->default(true)
                  ->comment('Si false, no se puede asignar ni mostrar');
            $table->boolean('is_visible_in_marketplace')->default(true)
                  ->comment('Si false, oculta de los chips/listados públicos pero sigue asignable');
            $table->boolean('is_leaf')->default(true)
                  ->comment('Calculado: true si no tiene hijos. Solo las leaf son seleccionables por sellers');
            $table->boolean('allow_seller_publish')->default(true)
                  ->comment('Permite que sellers publiquen productos directamente en esta categoría');

            // Orden + cache
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->unsignedInteger('listings_count_cache')->default(0)
                  ->comment('Conteo denormalizado de listings activos — actualizar via job/observer');

            $table->timestamps();

            $table->foreign('parent_id')
                  ->references('id')->on('marketplace_categories')
                  ->nullOnDelete();

            // Dos hermanos no pueden tener el mismo slug local
            $table->unique(['parent_id', 'slug']);
            // El full_slug es único globalmente
            $table->unique('full_slug');
        });
    }

    public function down()
    {
        Schema::dropIfExists('marketplace_categories');
    }
}
