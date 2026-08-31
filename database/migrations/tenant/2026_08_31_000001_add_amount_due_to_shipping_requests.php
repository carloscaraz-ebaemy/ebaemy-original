<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `amount_due` — lo que el cliente debe por ESTE envío, en total.
 *
 * El panel de pagos venía cobrando contra `delivery_price`, que es solo la
 * tarifa del servicio (S/ 20 tienda→agencia). Pero el cliente paga la
 * mercadería más el envío: con un pago de S/ 120 contra una tarifa de S/ 20 el
 * panel daba el envío por saldado y no había forma de saber cuánto faltaba
 * cobrar realmente, ni de cobrar en dos partes.
 *
 * Mismo nombre y mismo significado que en `orders` y `order_notes`
 * (ver `HasAmountDuePayments`): el monto que el cliente realmente debe,
 * ENVÍO INCLUIDO. `delivery_price` no se toca y sigue siendo la tarifa del
 * servicio, que se muestra como referencia.
 *
 * Nullable a propósito: los envíos históricos no lo tienen, y no se rellena
 * hacia atrás. Sin monto cargado el panel muestra lo cobrado y NO inventa un
 * saldo pendiente — una deuda falsa en 191 envíos vivos sería peor que no
 * mostrar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')
            || Schema::connection('tenant')->hasColumn('shipping_requests', 'amount_due')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
            $column = $table->decimal('amount_due', 12, 2)->nullable()
                ->comment('Monto total a cobrar al cliente por este envio (mercaderia + envio)');

            if (Schema::connection('tenant')->hasColumn('shipping_requests', 'delivery_price')) {
                $column->after('delivery_price');
            }
        });
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_requests')
            && Schema::connection('tenant')->hasColumn('shipping_requests', 'amount_due')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                $table->dropColumn('amount_due');
            });
        }
    }
};
