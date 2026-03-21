<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos logísticos a la tabla sale_notes.
 *
 * requires_warehouse_dispatch → TRUE  : el pedido pasa por la cola de almacén (logistic_status = PENDIENTE)
 * requires_warehouse_dispatch → FALSE : entrega inmediata en tienda          (logistic_status = ENTREGA_INMEDIATA)
 *
 * Los datos de courier se copian desde LogisticShippingGuide al despachar,
 * para que el voucher PDF los muestre sin joins adicionales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('sale_notes', function (Blueprint $table) {

            // ── Despacho por almacén ────────────────────────────────────────────
            $table->boolean('requires_warehouse_dispatch')
                  ->default(false)
                  ->after('filename')
                  ->comment('TRUE = pasa por cola de almacén; FALSE = entrega inmediata');

            $table->string('logistic_status', 30)
                  ->nullable()
                  ->after('requires_warehouse_dispatch')
                  ->comment('PENDIENTE | PREPARANDO | LISTO_DESPACHO | DESPACHADO | ENTREGA_INMEDIATA');

            // ── Usuario de almacén asignado ─────────────────────────────────────
            $table->unsignedBigInteger('warehouse_user_id')
                  ->nullable()
                  ->after('logistic_status')
                  ->comment('Usuario del almacén que procesa el pedido');

            // ── Datos del courier (se rellenan al despachar) ────────────────────
            $table->string('courier_name', 150)
                  ->nullable()
                  ->after('warehouse_user_id')
                  ->comment('Empresa / nombre del courier (Olva, Shalom, etc.)');

            $table->string('tracking_number', 100)
                  ->nullable()
                  ->after('courier_name')
                  ->comment('Número de guía / tracking del courier');

            $table->datetime('dispatch_date')
                  ->nullable()
                  ->after('tracking_number')
                  ->comment('Fecha y hora en que salió el pedido del almacén');

            // ── Índices para búsquedas frecuentes ──────────────────────────────
            $table->index('logistic_status',               'idx_sn_logistic_status');
            $table->index('requires_warehouse_dispatch',   'idx_sn_requires_dispatch');
            $table->index(['logistic_status', 'warehouse_user_id'], 'idx_sn_logistic_user');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('sale_notes', function (Blueprint $table) {
            $table->dropIndex('idx_sn_logistic_status');
            $table->dropIndex('idx_sn_requires_dispatch');
            $table->dropIndex('idx_sn_logistic_user');

            $table->dropColumn([
                'requires_warehouse_dispatch',
                'logistic_status',
                'warehouse_user_id',
                'courier_name',
                'tracking_number',
                'dispatch_date',
            ]);
        });
    }
};
