<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega el valor 'ANULADO' al ENUM logistic_status de la tabla sale_notes.
 * Permite anular un despacho y revertir el stock.
 */
return new class extends Migration
{
    public function getConnection(): string
    {
        return 'tenant';
    }

    public function up(): void
    {
        try {
            DB::connection('tenant')->statement(
                "ALTER TABLE sale_notes
                 MODIFY COLUMN logistic_status
                 ENUM('PENDIENTE','PREPARANDO','LISTO_DESPACHO','DESPACHADO','RECOGIDO','ENTREGA_INMEDIATA','ANULADO')
                 NULL"
            );
        } catch (\Throwable $e) {
            // ENUM ya contiene ANULADO o la columna no existe — ignorar
        }
    }

    public function down(): void
    {
        // Primero actualizar registros ANULADO para no romper el constraint
        DB::connection('tenant')->table('sale_notes')
            ->where('logistic_status', 'ANULADO')
            ->update(['logistic_status' => 'DESPACHADO']);

        try {
            DB::connection('tenant')->statement(
                "ALTER TABLE sale_notes
                 MODIFY COLUMN logistic_status
                 ENUM('PENDIENTE','PREPARANDO','LISTO_DESPACHO','DESPACHADO','RECOGIDO','ENTREGA_INMEDIATA')
                 NULL"
            );
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
