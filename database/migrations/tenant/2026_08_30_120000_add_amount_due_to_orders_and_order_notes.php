<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `amount_due` — monto que el cliente realmente debe por el pedido.
 *
 * El total del pedido sale de sumar los productos, pero hay pedidos donde los
 * productos no tienen precio cargado (trabajos a medida, encargos) y ese total
 * queda en cero o simplemente mal. El panel de pagos necesita saber contra
 * cuánto cobrar.
 *
 * Es una columna NUEVA a propósito, no un reemplazo de `total`:
 * OrderToSaleNoteService copia `$order->total` tal cual a la Nota de Venta, y
 * en order_notes `total` alimenta el PDF y los totales por ítem. Pisarlo
 * corrompería el comprobante generado y los reportes.
 *
 * Semántica: NULL = cobrar contra `total`, como siempre. Con valor = ese valor
 * manda para los pagos, y la pantalla muestra los dos para que se vea la
 * diferencia.
 */
return new class extends Migration
{
    /** Tablas que reciben la columna, cada una después de su `total`. */
    private const TABLES = ['orders', 'order_notes'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'amount_due')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $column = $t->decimal('amount_due', 12, 2)->nullable();

                if (Schema::hasColumn($table, 'total')) {
                    $column->after('total');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'amount_due')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('amount_due');
            });
        }
    }
};
