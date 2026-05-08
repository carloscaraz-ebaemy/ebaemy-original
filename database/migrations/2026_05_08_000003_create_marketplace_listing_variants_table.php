<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 0.B — Variantes de productos en marketplace central.
 *
 * Espeja `item_variants` del tenant para que el comprador pueda seleccionar
 * talla / color / etc. en /marketplace/p/{slug} sin tener que conectar
 * cross-DB en cada render.
 *
 * Llave por (listing_id, tenant_variant_id) — el sync hace upsert. Cuando
 * una variante se desactiva en el tenant, marcamos `is_active=false` aquí
 * (no DELETE) para conservar histórico de pedidos pasados.
 *
 *   listing_id        → marketplace_listings(id)
 *   tenant_variant_id → item_variants(id) en el tenant (no es FK porque
 *                       cruza BDs); el sync lo persiste para que el
 *                       dispatcher pueda usarlo al crear el order tenant.
 *   sku, display_name, image_url → del item_variants
 *   price             → precio efectivo (con promo si aplica)
 *   original_price    → precio sin descuento (para tachar en UI)
 *   is_on_offer, discount_pct, offer_ends_at → flags para badges/timer
 *   stock             → SUM(item_variant_warehouse.stock) o stock global
 *   is_active         → reflejo de item_variants.is_active
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_listing_variants')) {
            return;
        }

        Schema::create('marketplace_listing_variants', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('listing_id');
            $table->foreign('listing_id')
                  ->references('id')->on('marketplace_listings')
                  ->onDelete('cascade');

            $table->unsignedBigInteger('tenant_variant_id')
                  ->comment('id del item_variants en el tenant — sin FK porque cruza BDs');

            $table->string('sku', 100)->nullable();
            $table->string('display_name', 255);
            $table->string('image_url', 500)->nullable();

            $table->decimal('price', 10, 2);
            $table->decimal('original_price', 10, 2)->nullable();
            $table->boolean('is_on_offer')->default(false);
            $table->unsignedTinyInteger('discount_pct')->nullable();
            $table->timestamp('offer_ends_at')->nullable();

            $table->integer('stock')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['listing_id', 'tenant_variant_id'], 'mlv_listing_variant_uq');
            $table->index(['listing_id', 'is_active'], 'mlv_listing_active_idx');
            $table->index('is_on_offer', 'mlv_offer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listing_variants');
    }
};
