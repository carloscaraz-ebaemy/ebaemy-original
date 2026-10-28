<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clave de recojo fija de la tienda (4 dígitos) para las guías de Shalom.
 *
 * Hay tiendas que usan una sola clave para todos sus paquetes: con ella llena
 * no se genera una por guía. Vacía = una clave distinta por paquete.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_settings')
            || $schema->hasColumn('shipping_settings', 'shalom_default_clave')) {
            return;
        }

        $schema->table('shipping_settings', function (Blueprint $table) {
            $table->string('shalom_default_clave', 4)->nullable();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shipping_settings')
            && $schema->hasColumn('shipping_settings', 'shalom_default_clave')) {
            $schema->table('shipping_settings', function (Blueprint $table) {
                $table->dropColumn('shalom_default_clave');
            });
        }
    }
};
