<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remate de A-02: un canal de venta con pedidos dentro tiene que estar activo.
 *
 * La migración anterior (`backfill_marketplace_channel_on_orders`) creaba los
 * canales de marketplace externo INACTIVOS, por prudencia mal entendida. Pero
 * `is_active` no significa «ofrecerlo en el alta manual»: es lo que filtran
 * `OrderController::channels()` —el desplegable del panel— y `channelReport()`.
 * Con el canal inactivo, los 630 pedidos de Saga de carolayimport quedaban con
 * su `channel_id` correcto pero seguían sin poder filtrarse y seguían fuera del
 * reporte de ventas por canal, que es justo el sintoma que A-02 debia cerrar.
 *
 * Activa SOLO los canales que tienen al menos un pedido: un canal creado para
 * una integracion que nunca vendio nada (por ejemplo el feed de Meta, que
 * publica catalogo pero no recibe pedidos) sigue inactivo y no ensucia ni el
 * desplegable ni el reporte.
 *
 * Idempotente y conservadora: nunca desactiva nada.
 */
class ActivateSalesChannelsWithOrders extends Migration
{
    public function up()
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('sales_channels') || !$schema->hasColumn('orders', 'channel_id')) {
            return;
        }

        $db = DB::connection('tenant');

        // Canales inactivos que, pese a todo, tienen pedidos asignados.
        $conPedidos = $db->table('sales_channels as c')
            ->where('c.is_active', false)
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('orders as o')
                ->whereColumn('o.channel_id', 'c.id'))
            ->pluck('c.id');

        if ($conPedidos->isEmpty()) {
            return;
        }

        $db->table('sales_channels')
           ->whereIn('id', $conPedidos->all())
           ->update(['is_active' => true, 'updated_at' => now()]);
    }

    /**
     * No revierte: desactivar un canal con ventas volveria a esconderlas del
     * reporte, que es el bug que esto arregla.
     */
    public function down()
    {
        //
    }
}
