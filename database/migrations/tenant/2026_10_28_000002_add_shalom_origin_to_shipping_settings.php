<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Oficina de Shalom desde donde despacha la tienda (`ter_id`, ver
 * `shalom_agencies` en la base del sistema).
 *
 * El tiempo de viaje lo define la agencia, no la tienda: con el origen y la
 * oficina que elige el cliente se cotiza la ruta en Shalom y se le dice
 * cuándo llega de verdad, en vez de un rango que la tienda escribió a ojo.
 *
 * Idempotente: no todos los tenants tienen `shipping_settings`.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_settings')
            || $schema->hasColumn('shipping_settings', 'shalom_origin_id')) {
            return;
        }

        $schema->table('shipping_settings', function (Blueprint $table) {
            $table->unsignedInteger('shalom_origin_id')->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shipping_settings')
            && $schema->hasColumn('shipping_settings', 'shalom_origin_id')) {
            $schema->table('shipping_settings', function (Blueprint $table) {
                $table->dropColumn('shalom_origin_id');
            });
        }
    }
};
