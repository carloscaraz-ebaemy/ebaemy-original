<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Variante elegida por el comprador en cada línea del pedido del marketplace.
 *
 * La ficha ya dejaba elegir talla/color y el botón Comprar mandaba la
 * variante, pero el carrito la descartaba: el pedido llegaba a la tienda sin
 * saber qué talla despachar. Nulas: los productos sin variantes no las usan.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_order_items', 'listing_variant_id')) {
                $table->unsignedBigInteger('listing_variant_id')->nullable()->after('remote_item_id')->index();
            }
            if (!Schema::hasColumn('marketplace_order_items', 'tenant_variant_id')) {
                $table->unsignedBigInteger('tenant_variant_id')->nullable()->after('listing_variant_id');
            }
            if (!Schema::hasColumn('marketplace_order_items', 'variant_name')) {
                $table->string('variant_name', 150)->nullable()->after('tenant_variant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_order_items', function (Blueprint $table) {
            $table->dropColumn(['listing_variant_id', 'tenant_variant_id', 'variant_name']);
        });
    }
};
