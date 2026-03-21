<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pedidos logísticos — diferencian entrega inmediata (tienda) de despacho a provincia.
 * Se integra con la facturación existente a través de document_id / sale_note_id.
 *
 * Estados (OrderStatusEnum):
 *   pending → confirmed → in_preparation → dispatched → delivered
 *                              ↓
 *                          cancelled (solo antes de dispatched)
 *
 * Tipos de entrega (DeliveryTypeEnum):
 *   store    → descuenta stock físico y finaliza en el acto
 *   province → incrementa stock_committed, entra a cola del almacenero
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistic_orders', function (Blueprint $table) {
            $table->id();

            // Vínculo con el comprobante / nota de venta generado en el sistema
            $table->unsignedBigInteger('document_id')->nullable()->index()
                  ->comment('ID del documento/factura generado (documents table)');
            $table->unsignedBigInteger('sale_note_id')->nullable()->index()
                  ->comment('ID de la nota de venta (sale_notes table)');

            // Cliente
            $table->unsignedInteger('customer_id')->nullable()->index()
                  ->comment('ID de la persona/cliente (persons table)');

            // Usuario que crea la orden (cajero, ecommerce api, etc.)
            $table->unsignedInteger('user_id')->nullable();

            // Almacenero asignado para el picking
            $table->unsignedInteger('warehouse_user_id')->nullable()
                  ->comment('Usuario almacenero que toma el pedido');

            // Almacén de origen
            $table->unsignedInteger('warehouse_id')->index();

            // Tipo de entrega y estado
            $table->string('delivery_type', 20)->default('store')
                  ->comment('store | province');
            $table->string('status', 30)->default('pending')
                  ->comment('pending|confirmed|in_preparation|dispatched|delivered|cancelled');

            // Datos de envío provincia
            $table->string('destination_district', 100)->nullable();
            $table->string('destination_address')->nullable();
            $table->string('recipient_name', 150)->nullable();
            $table->string('recipient_phone', 20)->nullable();

            // Guía de remisión
            $table->unsignedBigInteger('shipping_guide_id')->nullable();

            // Montos
            $table->decimal('subtotal', 12, 4)->default(0);
            $table->decimal('igv', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);
            $table->string('currency_type_id', 3)->default('PEN');

            // Canal de origen
            $table->string('source', 20)->default('pos')
                  ->comment('pos | ecommerce | api');

            // Observaciones y motivo de cancelación
            $table->text('notes')->nullable();
            $table->string('cancel_reason')->nullable();

            // Timestamps de cambios de estado
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('preparation_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Índices compuestos
            $table->index(['status', 'delivery_type']);
            $table->index(['warehouse_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_orders');
    }
};
