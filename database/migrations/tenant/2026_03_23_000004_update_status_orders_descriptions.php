<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 9 — Actualizar descripciones de status_orders para flujo ecommerce claro.
 *
 * Mapa definitivo:
 *   1 → Pago pendiente       (era: "Pago sin verificar")
 *   2 → Pago verificado      (sin cambio)
 *   3 → En preparación       (era: "Despachado" — ahora = procesando antes de envío)
 *   4 → Enviado              (era: "Confirmado por el cliente")
 *   5 → Cancelado            (sin cambio)
 *   6 → Entregado            (NUEVO)
 *
 * Compatibilidad: solo actualiza texto, los IDs numéricos no cambian.
 * El código existente que usa status_order_id 1-5 sigue funcionando.
 */
class UpdateStatusOrdersDescriptions extends Migration
{
    private array $updates = [
        1 => 'Pago pendiente',
        2 => 'Pago verificado',
        3 => 'En preparación',
        4 => 'Enviado',
        5 => 'Cancelado',
    ];

    private array $rollback = [
        1 => 'Pago sin verificar',
        2 => 'Pago verificado',
        3 => 'Despachado',
        4 => 'Confirmado por el cliente',
        5 => 'Cancelado',
    ];

    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('status_orders')) return;

        foreach ($this->updates as $id => $description) {
            DB::connection('tenant')->table('status_orders')->where('id', $id)->update(['description' => $description]);
        }

        // Insertar "Entregado" solo si no existe
        if (!DB::connection('tenant')->table('status_orders')->where('id', 6)->exists()) {
            DB::connection('tenant')->table('status_orders')->insert([
                'id'          => 6,
                'description' => 'Entregado',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down()
    {
        foreach ($this->rollback as $id => $description) {
            DB::connection('tenant')->table('status_orders')->where('id', $id)->update(['description' => $description]);
        }

        DB::connection('tenant')->table('status_orders')->where('id', 6)->delete();
    }
}
