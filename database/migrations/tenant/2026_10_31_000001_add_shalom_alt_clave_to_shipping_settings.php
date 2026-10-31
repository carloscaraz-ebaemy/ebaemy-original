<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segunda clave de recojo de Shalom, que se turna con la fija.
 *
 * Shalom no acepta en una guía nueva la clave que se usó ayer (2026-10-31:
 * «No puedes usar la clave del día anterior»). Con dos claves, el sistema usa
 * la que no se usó ayer y la tienda sigue con una sola clave por día.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_settings')
            || $schema->hasColumn('shipping_settings', 'shalom_alt_clave')) {
            return;
        }

        $schema->table('shipping_settings', function (Blueprint $table) {
            $table->string('shalom_alt_clave', 4)->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shipping_settings')
            && $schema->hasColumn('shipping_settings', 'shalom_alt_clave')) {
            $schema->table('shipping_settings', function (Blueprint $table) {
                $table->dropColumn('shalom_alt_clave');
            });
        }
    }
};
