<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Stock Inteligente - Extiende item_warehouse con:
 *   stock_physical   → stock físico real en almacén
 *   stock_committed  → comprometido (pedidos provincia en cola, aún no despachados)
 *   stock_available  → calculado: stock_physical - stock_committed (sólo este vende el Ecommerce)
 *
 * Retro-compatible: migra el valor actual de `stock` a `stock_physical`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_warehouse', function (Blueprint $table) {
            $table->decimal('stock_physical', 12, 4)->default(0)->after('stock')
                  ->comment('Stock físico real en almacén');
            $table->decimal('stock_committed', 12, 4)->default(0)->after('stock_physical')
                  ->comment('Comprometido para pedidos provincia pendientes de despacho');
        });

        // Inicializa stock_physical con el valor actual de stock (retrocompatibilidad)
        DB::statement('UPDATE item_warehouse SET stock_physical = COALESCE(stock, 0)');
    }

    public function down(): void
    {
        Schema::table('item_warehouse', function (Blueprint $table) {
            $table->dropColumn(['stock_physical', 'stock_committed']);
        });
    }
};
