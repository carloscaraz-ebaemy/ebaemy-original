<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de recepción de mercancía sobre Órdenes de Compra (OC).
 *
 * Campos:
 *   reception_status   pending | partial | received
 *   received_at        timestamp del último movimiento de recepción
 *   received_by        user_id que confirmó la recepción
 *   reception_notes    observaciones (faltantes, daños, etc.)
 *
 * Y en purchase_order_items:
 *   quantity_received  acumulado recibido — permite recepciones parciales
 *
 * Diseño: las cantidades se acumulan; reception_status se calcula tras
 * cada recepción. Compatible con la lógica existente — todas las
 * columnas son nullable / con default y NO modifican OCs históricas.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_orders', 'reception_status')) {
                    $table->string('reception_status', 20)->default('pending')
                          ->after('state_type_id')
                          ->comment('pending | partial | received');
                    $table->index('reception_status', 'idx_po_reception_status');
                }
                if (!Schema::hasColumn('purchase_orders', 'received_at')) {
                    $table->timestamp('received_at')->nullable()->after('reception_status');
                }
                if (!Schema::hasColumn('purchase_orders', 'received_by')) {
                    $table->unsignedInteger('received_by')->nullable()->after('received_at');
                }
                if (!Schema::hasColumn('purchase_orders', 'reception_notes')) {
                    $table->string('reception_notes', 500)->nullable()->after('received_by');
                }
            });
        }

        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_order_items', 'quantity_received')) {
                    $table->decimal('quantity_received', 12, 4)->default(0)
                          ->after('quantity')
                          ->comment('Cantidad recibida acumulada — soporta recepciones parciales');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_orders')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                foreach (['reception_status', 'received_at', 'received_by', 'reception_notes'] as $col) {
                    if (Schema::hasColumn('purchase_orders', $col)) {
                        if ($col === 'reception_status') {
                            $table->dropIndex('idx_po_reception_status');
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('purchase_order_items')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_order_items', 'quantity_received')) {
                    $table->dropColumn('quantity_received');
                }
            });
        }
    }
};
