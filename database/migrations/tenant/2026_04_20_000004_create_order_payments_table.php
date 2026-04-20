<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla `order_payments` — replica la estructura de `sale_note_payments`
 * para registrar los pagos asociados a un pedido ecommerce.
 *
 * Al verificar el pago (transición 1→2), el admin puede registrar uno o varios
 * pagos con su método (efectivo, transferencia, tarjeta), banco destino, fecha
 * y referencia. Luego, cuando `OrderToSaleNoteService` genera la Nota de Venta,
 * estos registros se copian a `sale_note_payments` para mantener trazabilidad
 * completa con el comprobante.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('order_payments')) {
            return;
        }

        Schema::create('order_payments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->date('date_of_payment');
            $table->char('payment_method_type_id', 2);
            $table->boolean('has_card')->default(false);
            $table->char('card_brand_id', 2)->nullable();
            $table->string('reference')->nullable();
            $table->decimal('change', 12, 2)->nullable();
            $table->decimal('payment', 12, 2);
            // Destino del pago: "cash" o un bank_account_id. Se guarda como string
            // para aceptar el valor "cash" (caja) además de IDs numéricos.
            $table->string('payment_destination_id', 50)->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->foreign('card_brand_id')->references('id')->on('card_brands');
            $table->foreign('payment_method_type_id')->references('id')->on('payment_method_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_payments');
    }
};
