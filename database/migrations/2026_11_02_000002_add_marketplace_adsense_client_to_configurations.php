<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Google AdSense en el marketplace: el ID del editor (ca-pub-XXXXXXXX).
 * Se pega desde /admin/marketplace/seo; con él el layout carga el script de
 * AdSense y /ads.txt responde con la línea que Google exige.
 */
return new class extends Migration
{
    protected $connection = 'system';

    public function up()
    {
        if (!Schema::connection('system')->hasColumn('configurations', 'marketplace_adsense_client')) {
            Schema::connection('system')->table('configurations', function (Blueprint $table) {
                $table->string('marketplace_adsense_client', 40)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::connection('system')->hasColumn('configurations', 'marketplace_adsense_client')) {
            Schema::connection('system')->table('configurations', function (Blueprint $table) {
                $table->dropColumn('marketplace_adsense_client');
            });
        }
    }
};
