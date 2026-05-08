<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hover-image en cards del marketplace.
 *
 * Permite que al pasar el cursor sobre una card del listado se vea la
 * segunda imagen del producto (galería) sin tener que entrar al detalle.
 * Patrón típico de ecommerce moderno (Mercado Libre, Falabella).
 *
 * Se llena en MarketplaceListingSyncService::buildPayload tomando la
 * primera fila de item_images del tenant. Si el item solo tiene una
 * imagen, queda NULL y la UI cae al efecto hover normal sin cambio.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'secondary_image_url')) {
                $table->string('secondary_image_url', 500)->nullable()->after('image_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_listings', 'secondary_image_url')) {
                $table->dropColumn('secondary_image_url');
            }
        });
    }
};
