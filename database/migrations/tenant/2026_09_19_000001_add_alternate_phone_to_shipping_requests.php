<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teléfono adicional del envío.
 *
 * `phone` es el del destinatario y no se toca. El adicional es otro dato y de
 * otro dueño: el vecino que recibe, el familiar que acompaña, el segundo
 * número al que llamar cuando el primero no contesta. Hasta ahora no existía
 * en ninguna parte —ni en `shipping_requests` ni en el JSON del pedido— y en
 * la práctica se acababa escribiendo dentro de `notes`, donde ni se puede
 * buscar ni sale en el rótulo.
 *
 * Vive en el envío y no en el cliente a propósito: el cliente es siempre el
 * mismo, el segundo contacto cambia con cada entrega.
 *
 * Idempotente (`hasColumn`): hay 17 tenants y el módulo de Envíos no está
 * instalado en todos.
 */
return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('shipping_requests')) {
            return;
        }

        if ($schema->hasColumn('shipping_requests', 'alternate_phone')) {
            return;
        }

        $schema->table('shipping_requests', function (Blueprint $table) {
            $table->string('alternate_phone', 20)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if ($schema->hasTable('shipping_requests')
            && $schema->hasColumn('shipping_requests', 'alternate_phone')) {
            $schema->table('shipping_requests', function (Blueprint $table) {
                $table->dropColumn('alternate_phone');
            });
        }
    }
};
