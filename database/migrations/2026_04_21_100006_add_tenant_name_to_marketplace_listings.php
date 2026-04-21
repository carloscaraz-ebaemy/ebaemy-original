<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el nombre comercial (trade_name/name) de la tienda que publica cada
 * listing para mostrar en la vitrina "Vendido por {Marca}" en vez del fqdn
 * técnico. Se sincroniza desde el Company del tenant en cada sync.
 */
class AddTenantNameToMarketplaceListings extends Migration
{
    public function up()
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'tenant_name')) {
                $table->string('tenant_name', 150)->nullable()->after('tenant_fqdn')
                      ->comment('Nombre comercial de la tienda vendedora (trade_name)');
            }
            if (!Schema::hasColumn('marketplace_listings', 'tenant_logo_url')) {
                $table->string('tenant_logo_url', 500)->nullable()->after('tenant_name')
                      ->comment('URL absoluta al logo de la tienda');
            }
        });
    }

    public function down()
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            foreach (['tenant_name', 'tenant_logo_url'] as $col) {
                if (Schema::hasColumn('marketplace_listings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
