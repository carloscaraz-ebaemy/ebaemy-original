<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `order_note_payments` — pagos de un Pedido del ERP.
 *
 * Réplica de `order_payments` (que a su vez replica `sale_note_payments`), para
 * que las tres pantallas registren un pago con los mismos campos: fecha, método,
 * destino, referencia, monto y archivo adjunto.
 *
 * Hasta ahora order_notes solo guardaba un `payment_method_type_id` suelto en la
 * cabecera: no se podía registrar más de un pago, ni parciales, ni saber el
 * saldo. Esa columna se conserva para no romper nada que la lea.
 *
 * El archivo del pago NO vive acá: va en `payment_files` por relación
 * polimórfica (payment_id + payment_type), igual que en nota de venta.
 * Lo mismo el asiento en Finanzas, vía GlobalPayment.
 *
 * FKs con unsignedInteger: `order_notes`, `payment_method_types` y `card_brands`
 * son tablas legacy con increments()/char, no bigIncrements.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_note_payments') || !Schema::hasTable('order_notes')) {
            return;
        }

        Schema::create('order_note_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_note_id');
            $table->date('date_of_payment');
            $table->char('payment_method_type_id', 2);
            $table->boolean('has_card')->default(false);
            $table->char('card_brand_id', 2)->nullable();
            $table->string('reference')->nullable();
            $table->decimal('change', 12, 2)->nullable();
            $table->decimal('payment', 12, 2);
            // "cash" (caja) o un bank_account_id. String para aceptar los dos,
            // mismo criterio que order_payments.
            $table->string('payment_destination_id', 50)->nullable();
            $table->timestamps();

            $table->index('order_note_id');
            $table->foreign('order_note_id')->references('id')->on('order_notes')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_note_payments');
    }
};
