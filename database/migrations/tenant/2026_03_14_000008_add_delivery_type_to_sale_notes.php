<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Agrega delivery_type a sale_notes para diferenciar:
 *   - store         : Entrega inmediata en mostrador
 *   - pickup        : Cliente paga y vuelve a recoger
 *   - province      : Envío vía courier
 *
 * También agrega campo is_urgent para solicitudes urgentes al almacén.
 * Y actualiza el ENUM de logistic_status para incluir RECOGIDO.
 */
class AddDeliveryTypeToSaleNotes extends Migration
{
    public function up(): void
    {
        // 1. delivery_type en sale_notes
        Schema::connection('tenant')->table('sale_notes', function (Blueprint $table) {
            $table->string('delivery_type', 20)->nullable()->after('requires_warehouse_dispatch');
            $table->boolean('is_urgent')->default(false)->after('delivery_type');
            $table->string('pickup_person', 150)->nullable()->after('is_urgent');
        });

        // 2. Actualizar ENUM de logistic_status para incluir RECOGIDO
        DB::connection('tenant')->statement(
            "ALTER TABLE sale_notes MODIFY COLUMN logistic_status
             ENUM('PENDIENTE','PREPARANDO','LISTO_DESPACHO','DESPACHADO','RECOGIDO','ENTREGA_INMEDIATA')
             NULL DEFAULT NULL"
        );

        // 3. Migrar datos existentes: asignar delivery_type según requires_warehouse_dispatch
        DB::connection('tenant')->statement("
            UPDATE sale_notes
            SET delivery_type = CASE
                WHEN requires_warehouse_dispatch = 1 THEN 'province'
                ELSE 'store'
            END
            WHERE delivery_type IS NULL
        ");
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('sale_notes', function (Blueprint $table) {
            $table->dropColumn(['delivery_type', 'is_urgent', 'pickup_person']);
        });

        DB::connection('tenant')->statement(
            "ALTER TABLE sale_notes MODIFY COLUMN logistic_status
             ENUM('PENDIENTE','PREPARANDO','LISTO_DESPACHO','DESPACHADO','ENTREGA_INMEDIATA')
             NULL DEFAULT NULL"
        );
    }
}
