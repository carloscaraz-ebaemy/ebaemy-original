<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paquete típico de la tienda para estimar el flete de Shalom.
 *
 * El rango de la cotización va del sobre a la caja L, y el cliente veía
 * «desde S/ 8» aunque la tienda manda cajas grandes. Con esto el formulario
 * muestra el precio aproximado del paquete que la tienda realmente envía.
 *
 * JSON: {"type":"size","size":"PAQUETE L"} o
 *       {"type":"bulto","length":80,"width":60,"height":60,"weight":30}
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shipping_settings') && !$schema->hasColumn('shipping_settings', 'shalom_package')) {
            $schema->table('shipping_settings', function (Blueprint $table) {
                $table->json('shalom_package')->nullable();
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');
        if ($schema->hasColumn('shipping_settings', 'shalom_package')) {
            $schema->table('shipping_settings', fn (Blueprint $t) => $t->dropColumn('shalom_package'));
        }
    }
};
