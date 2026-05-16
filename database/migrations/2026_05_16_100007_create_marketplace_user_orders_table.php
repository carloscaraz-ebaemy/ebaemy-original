<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot agregado de pedidos del comprador cross-tenant.
 *
 * El tenant es fuente de verdad — esta tabla solo guarda el agregado
 * minimo para personalizacion (no lineas, no detalle).
 *
 * Push desde tenant al system via job al confirmar pedido. El system
 * NO hace pull. El pedido en el tenant NUNCA falla si el system esta
 * caido (el job se encola y reintenta).
 *
 * Webhook desde tenant en cambios de estado relevantes (cancelado,
 * devuelto, completado).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_user_orders')) return;

        Schema::create('marketplace_user_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('hostname_id');
            // ID en el tenant. unsignedInteger porque tenant.documents/orders
            // tipicamente tiene id INT (legacy).
            $table->unsignedInteger('order_id');
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('PEN');
            $table->string('status', 32)->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedSmallInteger('items_count')->default(0);
            // Snapshot de category_ids para personalizacion sin joinear
            // de vuelta al tenant. ej. [3, 17, 42].
            $table->json('product_categories')->nullable();
            $table->timestamps();

            $table->unique(['hostname_id', 'order_id'], 'mkt_orders_tenant_unique');
            $table->index(['user_id', 'confirmed_at'], 'mkt_orders_user_time_idx');

            $table->foreign('user_id')
                  ->references('id')->on('marketplace_users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_user_orders');
    }
};
