<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca cuándo se devolvió el stock de un pedido de marketplace cancelado/
 * devuelto (para no restituirlo dos veces). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_orders')) {
            return;
        }
        Schema::table('marketplace_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_orders', 'stock_restored_at')) {
                $table->timestamp('stock_restored_at')->nullable()->after('invoice_upload_error');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_orders') && Schema::hasColumn('marketplace_orders', 'stock_restored_at')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                $table->dropColumn('stock_restored_at');
            });
        }
    }
};
