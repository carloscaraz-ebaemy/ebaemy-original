<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase C — el operador puede corregir con qué se documenta un pedido.
 *
 * Hasta ahora el tipo de comprobante lo elegía el COMPRADOR en el checkout y
 * quedaba en `orders.purchase`, un JSON que es la foto de ese momento. El
 * operador no podía tocarlo, y eso rompe en casos que pasan todos los días: el
 * cliente marcó «boleta» y después dio un RUC; el pedido llegó de un canal que
 * no manda el dato; el pedido se cargó a mano.
 *
 * ── Por qué columnas nuevas y no `purchase` ───────────────────────────────
 *
 * `purchase` NO se toca. Es lo que el comprador declaró, y sobrescribirlo
 * borraría la única prueba de lo que pidió. La decisión del operador es un
 * hecho distinto, posterior y con autor: vive aparte y queda auditada.
 *
 * `billing_customer` guarda solo los datos tributarios corregidos (número de
 * documento y razón social). No duplica al cliente: `orders.customer` sigue
 * siendo la foto del checkout y `person_id` el enlace vivo a la cartera.
 *
 * Todas las columnas son nullable: un pedido sin corrección se comporta
 * exactamente como hoy, resolviendo el tipo desde el documento del cliente.
 *
 * Idempotente y sin datos: no rellena nada hacia atrás. Inventar una elección
 * de operador que nunca ocurrió sería falsear una auditoría.
 */
class AddBillingChoiceToOrders extends Migration
{
    public function up()
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('orders')) {
            return;
        }

        $schema->table('orders', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('orders', 'billing_document_type_id')) {
                // Códigos SUNAT: 80 nota de venta, 03 boleta, 01 factura.
                $table->char('billing_document_type_id', 2)->nullable()->after('purchase');
            }

            if (!$schema->hasColumn('orders', 'billing_customer')) {
                $table->json('billing_customer')->nullable()->after('billing_document_type_id');
            }

            if (!$schema->hasColumn('orders', 'billing_set_by')) {
                // `unsignedInteger` y sin FK: `users` es una tabla heredada con
                // `int(10) unsigned`, y un `foreignId()` no casa con ella.
                $table->unsignedInteger('billing_set_by')->nullable()->after('billing_customer');
            }

            if (!$schema->hasColumn('orders', 'billing_set_at')) {
                $table->timestamp('billing_set_at')->nullable()->after('billing_set_by');
            }
        });
    }

    public function down()
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('orders')) {
            return;
        }

        $columnas = array_values(array_filter(
            ['billing_document_type_id', 'billing_customer', 'billing_set_by', 'billing_set_at'],
            fn ($c) => $schema->hasColumn('orders', $c)
        ));

        if ($columnas) {
            $schema->table('orders', fn (Blueprint $table) => $table->dropColumn($columnas));
        }
    }
}
