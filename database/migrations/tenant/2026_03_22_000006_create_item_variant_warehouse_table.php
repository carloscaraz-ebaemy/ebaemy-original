<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stock por variante + almacén.
 *
 * Espejo exacto de item_warehouse pero para variantes.
 * Mismos campos: stock (legacy), stock_physical, stock_committed.
 * stock_available se computa: max(0, stock_physical - stock_committed)
 *
 * Cuando has_variants=true:
 *   - item_warehouse.stock = SUM(item_variant_warehouse.stock) por almacén
 *   - items.stock = SUM(item_variant_warehouse.stock) total
 *
 * Esto mantiene retrocompatibilidad con todo el código existente que
 * lee item_warehouse o items.stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_variant_warehouse')) return;

        Schema::create('item_variant_warehouse', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('item_variant_id');
            $table->foreign('item_variant_id')
                  ->references('id')->on('item_variants')
                  ->onDelete('cascade');

            $table->unsignedInteger('warehouse_id');
            $table->foreign('warehouse_id')
                  ->references('id')->on('warehouses')
                  ->onDelete('cascade');

            // Stock legacy (retrocompat) — se mantiene sincronizado con stock_physical
            $table->decimal('stock', 12, 4)->default(0);

            // Smart Stock (mismo esquema que item_warehouse)
            $table->decimal('stock_physical', 12, 4)->default(0)
                ->comment('Stock físico real en el almacén');
            $table->decimal('stock_committed', 12, 4)->default(0)
                ->comment('Reservado por pedidos provincia/ecommerce pendientes');

            $table->timestamps();

            // Una variante solo tiene una fila por almacén
            $table->unique(
                ['item_variant_id', 'warehouse_id'],
                'uq_variant_warehouse'
            );

            $table->index('item_variant_id', 'idx_ivw_variant');
            $table->index('warehouse_id',    'idx_ivw_warehouse');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variant_warehouse');
    }
};
