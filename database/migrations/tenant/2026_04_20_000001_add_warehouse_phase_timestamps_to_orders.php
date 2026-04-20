<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega timestamps del ciclo de despacho ecommerce a la tabla `orders`.
 *
 *   prepared_at   → se setea al pasar status_order_id 2→3 (reserva stock_committed)
 *   dispatched_at → se setea al pasar 3→4 (descuenta stock_physical)
 *   delivered_at  → se setea al pasar 4→6 (entrega confirmada)
 *
 * Estos timestamps permiten al sistema distinguir en qué fase del flujo de
 * almacén se encuentra un pedido — imprescindible para que las transiciones
 * sean idempotentes y no produzcan doble descuento de stock cuando se
 * migren pedidos antiguos (que solo tenían status_order_id).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'prepared_at')) {
                $table->timestamp('prepared_at')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'dispatched_at')) {
                $table->timestamp('dispatched_at')->nullable()->after('prepared_at');
            }
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('dispatched_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['prepared_at', 'dispatched_at', 'delivered_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
