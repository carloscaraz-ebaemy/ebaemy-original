<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `reference_payment` es lo que la columna «Medio pago» del panel de Pedidos
 * pinta en crudo, y el backfill de encargos logísticos le metió ahí el ORIGEN
 * del pedido: 231 filas mostrando literalmente «registro_envio» como si fuera
 * una forma de cobro.
 *
 * El origen ya consta en dos sitios correctos —`channel_id` (ENV01) y
 * `customer.source`— así que aquí solo sobra. Se vacía en vez de borrarse la
 * columna: `reference_payment` es NOT NULL y otros canales sí la usan para lo
 * que es (`efectivo`, `culqi`, `marketplace`).
 *
 * Acotada a los pedidos del canal ENV01: no toca el medio de pago de ningún
 * pedido de venta real.
 *
 * Idempotente.
 */
class ClearOriginFromReferencePayment extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('sales_channels')
            || !Schema::connection('tenant')->hasColumn('orders', 'channel_id')) {
            return;
        }

        $db = DB::connection('tenant');

        $canalId = $db->table('sales_channels')->where('code', 'ENV01')->value('id');
        if (!$canalId) {
            return;
        }

        $db->table('orders')
           ->where('channel_id', $canalId)
           ->where('reference_payment', 'registro_envio')
           ->update(['reference_payment' => '']);
    }

    /**
     * No revierte: restaurar la cadena volvería a mostrar un origen en la
     * columna de medios de pago, que es el defecto que esto corrige.
     */
    public function down()
    {
        //
    }
}
