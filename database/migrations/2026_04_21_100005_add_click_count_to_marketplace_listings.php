<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de click-throughs al storefront del tenant.
 * Cada vez que un cliente hace click en "Comprar ahora" en ebaemy.com se
 * incrementa click_count y se redirige al tenant con UTM. El tenant factura
 * la venta por su cuenta; el central solo mide tráfico generado.
 */
class AddClickCountToMarketplaceListings extends Migration
{
    public function up()
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'click_count')) {
                $table->unsignedInteger('click_count')->default(0)->after('lead_count')
                      ->comment('Click-throughs al storefront del tenant');
            }
        });
    }

    public function down()
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_listings', 'click_count')) {
                $table->dropColumn('click_count');
            }
        });
    }
}
