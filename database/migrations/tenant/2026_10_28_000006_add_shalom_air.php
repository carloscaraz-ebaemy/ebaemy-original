<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envío aéreo por Shalom.
 *
 * - shipping_requests.shalom_air: el cliente eligió vía aérea (más rápido,
 *   más caro). La guía se crea con destino aéreo («0» + ter_id).
 * - shipping_settings.shalom_air_enabled: la tienda ofrece la opción aérea
 *   en su formulario (encendida por defecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shipping_requests') && !$schema->hasColumn('shipping_requests', 'shalom_air')) {
            $schema->table('shipping_requests', function (Blueprint $table) {
                $table->boolean('shalom_air')->default(false)->after('agency_office_address');
            });
        }
        if ($schema->hasTable('shipping_settings') && !$schema->hasColumn('shipping_settings', 'shalom_air_enabled')) {
            $schema->table('shipping_settings', function (Blueprint $table) {
                $table->boolean('shalom_air_enabled')->default(true);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        if ($schema->hasColumn('shipping_requests', 'shalom_air')) {
            $schema->table('shipping_requests', fn (Blueprint $t) => $t->dropColumn('shalom_air'));
        }
        if ($schema->hasColumn('shipping_settings', 'shalom_air_enabled')) {
            $schema->table('shipping_settings', fn (Blueprint $t) => $t->dropColumn('shalom_air_enabled'));
        }
    }
};
