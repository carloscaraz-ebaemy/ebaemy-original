<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 0.C — Variantes agrupadas por tipo de opción (estilo Falabella/MercadoLibre).
 *
 * 3 tablas espejo de la estructura del tenant:
 *
 *   marketplace_listing_options
 *     Una fila por opción del producto (Color, Talla, Material, etc.).
 *     Espejo de item_options del tenant.
 *
 *   marketplace_listing_option_values
 *     Una fila por valor de opción (Rojo, Azul, M, L, etc.). Persiste
 *     image_url cuando esa opción tiene una imagen representativa
 *     (usada para los thumbnails-imagen del color al estilo Falabella).
 *     color_hex se preserva por si el valor es solo un código de color.
 *
 *   marketplace_listing_variant_values
 *     Pivote (listing_variant_id, option_value_id). Permite resolver la
 *     variante exacta cuando el cliente elige Color=Rojo + Talla=M.
 *
 * Sin FK al tenant — los IDs locales del tenant se preservan en
 * `tenant_*_id` para que el dispatcher pueda mapear de vuelta al
 * crear el order en el tenant correcto.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_listing_options')) {
            Schema::create('marketplace_listing_options', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('listing_id');
                $table->foreign('listing_id')
                      ->references('id')->on('marketplace_listings')
                      ->onDelete('cascade');
                $table->unsignedBigInteger('tenant_option_id')
                      ->comment('id de item_options en el tenant');
                $table->string('name', 80)->comment('Color, Talla, Material...');
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();

                $table->unique(['listing_id', 'tenant_option_id'], 'mlo_listing_tenant_uq');
                $table->index('listing_id', 'mlo_listing_idx');
            });
        }

        if (!Schema::hasTable('marketplace_listing_option_values')) {
            Schema::create('marketplace_listing_option_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('option_id');
                $table->foreign('option_id')
                      ->references('id')->on('marketplace_listing_options')
                      ->onDelete('cascade');
                $table->unsignedBigInteger('tenant_value_id')
                      ->comment('id de item_option_values en el tenant');
                $table->string('value', 100)->comment('Rojo, M, L...');
                $table->string('color_hex', 7)->nullable()->comment('#RRGGBB cuando aplica');
                $table->string('image_url', 500)->nullable()->comment('Thumb del producto en este color/valor');
                $table->unsignedSmallInteger('position')->default(0);
                $table->timestamps();

                $table->unique(['option_id', 'tenant_value_id'], 'mlov_option_tenant_uq');
                $table->index('option_id', 'mlov_option_idx');
            });
        }

        if (!Schema::hasTable('marketplace_listing_variant_values')) {
            Schema::create('marketplace_listing_variant_values', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('listing_variant_id');
                $table->foreign('listing_variant_id')
                      ->references('id')->on('marketplace_listing_variants')
                      ->onDelete('cascade');
                $table->unsignedBigInteger('option_value_id');
                $table->foreign('option_value_id')
                      ->references('id')->on('marketplace_listing_option_values')
                      ->onDelete('cascade');
                $table->timestamps();

                $table->unique(['listing_variant_id', 'option_value_id'], 'mlvv_variant_value_uq');
                $table->index('listing_variant_id', 'mlvv_variant_idx');
                $table->index('option_value_id',    'mlvv_value_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listing_variant_values');
        Schema::dropIfExists('marketplace_listing_option_values');
        Schema::dropIfExists('marketplace_listing_options');
    }
};
