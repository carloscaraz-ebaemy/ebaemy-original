<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca cuándo la venta de un pedido de marketplace descontó stock en el ERP.
 *
 * Hasta 2026-11-17 el descuento buscaba el producto por ShopSku (código de
 * Falabella) y nunca encontraba nada: ningún pedido anterior descontó. La
 * devolución solo repone si este campo está puesto, para no sumar unidades que
 * nunca se restaron.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_orders')) {
            return;
        }
        Schema::table('marketplace_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_orders', 'stock_deducted_at')) {
                $table->timestamp('stock_deducted_at')->nullable()->after('invoice_upload_error');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_orders') && Schema::hasColumn('marketplace_orders', 'stock_deducted_at')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                $table->dropColumn('stock_deducted_at');
            });
        }
    }
};
