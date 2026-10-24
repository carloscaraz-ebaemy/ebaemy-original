<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resumen de la política de envío de la tienda en cada listing del marketplace.
 *
 * Es de la tienda, no del producto, pero se copia en cada listing — como
 * `tenant_name` o `seller_whatsapp` — para que la ficha, las cards, el carrito y
 * el feed lo lean sin cruzar a la base del tenant. Lo escribe
 * MarketplaceListingSyncService (buildPayload y, al guardar la política,
 * refreshShipping). NULL = la tienda no lo confirmó: se muestra «A coordinar
 * con la tienda», que es la verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_listings') && !Schema::hasColumn('marketplace_listings', 'shipping')) {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                $table->json('shipping')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_listings') && Schema::hasColumn('marketplace_listings', 'shipping')) {
            Schema::table('marketplace_listings', fn (Blueprint $t) => $t->dropColumn('shipping'));
        }
    }
};
