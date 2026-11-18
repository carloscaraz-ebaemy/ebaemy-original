<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base `system`. Galería de cada variante publicada en el marketplace: lista
 * JSON de URLs absolutas (la principal de la variante primero y después sus
 * fotos adicionales de `item_variant_images`). La llena
 * MarketplaceListingSyncService::syncVariants(); la ficha la muestra al elegir
 * esa variante.
 *
 * NULL = la variante no tiene galería propia (sin fotos, o el vendedor marcó
 * «usar la imagen del producto»): la ficha sigue con la galería del producto.
 *
 * Aditiva, nullable e idempotente.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_listing_variants')) return;
        if (Schema::hasColumn('marketplace_listing_variants', 'gallery_image_urls')) return;

        Schema::table('marketplace_listing_variants', function (Blueprint $table) {
            $table->json('gallery_image_urls')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_listing_variants')) return;
        if (!Schema::hasColumn('marketplace_listing_variants', 'gallery_image_urls')) return;

        Schema::table('marketplace_listing_variants', function (Blueprint $table) {
            $table->dropColumn('gallery_image_urls');
        });
    }
};
