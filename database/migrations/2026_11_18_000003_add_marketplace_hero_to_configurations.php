<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Portada de temporada del inicio del marketplace (fase 2 del rediseño).
 * Un JSON con activo, título, subtítulo, botón, destino, categoría de las
 * fotos, ids de productos y fechas; se edita en /admin/marketplace/portada.
 * Idempotente: si la columna existe no la toca, y solo rellena el valor por
 * defecto («Navidad en casa» → categoría oficial navidad) donde está vacío.
 */
return new class extends Migration
{
    protected $connection = 'system';

    public function up()
    {
        if (!Schema::connection('system')->hasColumn('configurations', 'marketplace_hero')) {
            Schema::connection('system')->table('configurations', function (Blueprint $table) {
                $table->json('marketplace_hero')->nullable();
            });
        }

        DB::connection('system')->table('configurations')
            ->whereNull('marketplace_hero')
            ->update(['marketplace_hero' => json_encode(\App\Services\Marketplace\HomeShowcase::HERO_DEFAULTS, JSON_UNESCAPED_UNICODE)]);

        \Illuminate\Support\Facades\Cache::forget('system_config');
    }

    public function down()
    {
        if (Schema::connection('system')->hasColumn('configurations', 'marketplace_hero')) {
            Schema::connection('system')->table('configurations', function (Blueprint $table) {
                $table->dropColumn('marketplace_hero');
            });
        }
    }
};
