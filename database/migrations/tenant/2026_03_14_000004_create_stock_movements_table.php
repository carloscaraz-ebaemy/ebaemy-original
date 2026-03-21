<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de auditoría de movimientos de stock inteligente.
 *
 * Tipos (StockMovementTypeEnum):
 *   sale_store         → venta tienda (descuenta physical)
 *   province_commit    → pedido provincia (incrementa committed)
 *   province_dispatch  → despacho provincia (descuenta committed + physical)
 *   province_cancel    → cancelación provincia (devuelve committed)
 *   purchase           → entrada por compra (incrementa physical)
 *   adjustment         → ajuste manual
 *   transfer           → transferencia entre almacenes
 *   return             → devolución
 *   ecommerce_reserve  → reserva ecommerce checkout (incrementa committed)
 *   ecommerce_cancel   → liberación reserva ecommerce (devuelve committed)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('item_id')->index();
            $table->unsignedInteger('warehouse_id')->index();
            $table->unsignedInteger('user_id')->nullable();

            // Tipo de movimiento
            $table->string('type', 40)->index();

            // Cantidades afectadas (positivo = entrada, negativo = salida)
            $table->decimal('qty_physical', 12, 4)->default(0)
                  ->comment('Delta aplicado a stock_physical');
            $table->decimal('qty_committed', 12, 4)->default(0)
                  ->comment('Delta aplicado a stock_committed');

            // Snapshot post-movimiento (para auditoría)
            $table->decimal('stock_physical_after', 12, 4)->default(0);
            $table->decimal('stock_committed_after', 12, 4)->default(0);
            $table->decimal('stock_available_after', 12, 4)->default(0)
                  ->comment('physical - committed al momento del movimiento');

            // Referencia polimórfica (LogisticOrder, Purchase, Document, SaleNote, etc.)
            $table->nullableMorphs('reference');

            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'warehouse_id', 'created_at']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
