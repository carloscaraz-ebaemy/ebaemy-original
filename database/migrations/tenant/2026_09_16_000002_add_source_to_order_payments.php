<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De dónde viene un cobro: lo tecleó una persona o lo trajo una integración.
 *
 * Hasta ahora `order_payments` no lo distinguía, y esa era la raíz del problema:
 * un pedido de Saga entraba en «pago verificado» SIN ningún cobro registrado, así
 * que su saldo seguía siendo el total y el panel invitaba a cobrarlo otra vez —
 * y lo aceptaba, porque el único guardarrail contra el sobrepago es el saldo.
 *
 * Con el origen en la fila, el cobro del canal puede existir de verdad: el saldo
 * queda en cero y el guardarrail que ya protege a los pedidos manuales protege a
 * los de Saga sin una sola regla nueva.
 *
 *   source              MANUAL (lo registró un usuario) | SAGA (lo cobró el canal)
 *   external_reference  el identificador del cobro en el sistema de origen
 *
 * `external_reference` es además la clave de idempotencia: el único por
 * (order_id, external_reference) impide que reimportar el mismo pedido siembre
 * dos veces el mismo cobro. En MySQL varios NULL conviven en un índice único, así
 * que los cobros manuales —que no tienen referencia externa— no se estorban.
 *
 * Idempotente. Las filas existentes quedan como MANUAL, que es exactamente lo que
 * son: hasta hoy ninguna integración creaba cobros.
 */
return new class extends Migration
{
    const INDEX = 'order_payments_order_external_unique';

    public function up(): void
    {
        // Conexión explícita del tenant: `Schema::getConnection()` devuelve la
        // conexión por defecto (la BD del sistema), no la del tenant.
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('order_payments')) {
            return;
        }

        if (!$schema->hasColumn('order_payments', 'source')) {
            $schema->table('order_payments', function (Blueprint $table) {
                $table->string('source', 20)->default('MANUAL')->after('created_by');
            });
        }

        if (!$schema->hasColumn('order_payments', 'external_reference')) {
            $schema->table('order_payments', function (Blueprint $table) {
                $table->string('external_reference', 100)->nullable()->after('source');
            });
        }

        $existe = !empty($schema->getConnection()->select(
            'SHOW INDEX FROM order_payments WHERE Key_name = ?',
            [self::INDEX]
        ));

        if (!$existe) {
            $schema->table('order_payments', function (Blueprint $table) {
                $table->unique(['order_id', 'external_reference'], self::INDEX);
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('order_payments')) {
            return;
        }

        $existe = !empty($schema->getConnection()->select(
            'SHOW INDEX FROM order_payments WHERE Key_name = ?',
            [self::INDEX]
        ));

        if ($existe) {
            $schema->table('order_payments', function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }

        foreach (['external_reference', 'source'] as $columna) {
            if ($schema->hasColumn('order_payments', $columna)) {
                $schema->table('order_payments', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }
};
