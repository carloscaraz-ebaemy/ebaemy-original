<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A-02 de la auditoría de Pedidos — los pedidos de un marketplace externo
 * (Saga/Falabella) entraban con `channel_id` NULL.
 *
 * `MarketplaceOrder::createErpOrder()` buscaba su canal de venta con
 * `name LIKE '%falabella%'`, y los únicos canales que siembran las migraciones
 * son ECOM, POS01, WHA01, TEL01 y MKP01 («Marketplace ebaemy»). Ninguno
 * contiene «falabella», así que la búsqueda no encontraba nada nunca y el
 * pedido se creaba sin canal: invisible para el filtro de canal del panel y
 * ausente de `channelReport()`. En producción eran los 630 pedidos de
 * carolayimport, el 100 % de ese tenant.
 *
 * El código ya está arreglado (`SalesChannel::marketplacePlatformChannel()`).
 * Esta migración se ocupa de lo que ya está grabado:
 *   1. Crea el canal de venta de cada marketplace externo configurado.
 *   2. Rellena `channel_id` en los pedidos que vinieron de ese canal.
 *
 * Solo toca pedidos con `channel_id` NULL Y con un pedido de marketplace
 * asociado: nunca reasigna un canal existente ni adivina el de un pedido
 * cuyo origen no consta. Los pedidos antiguos sin canal y sin marketplace
 * (por ejemplo, los 20 de alasitas anteriores al refactor de canales) se dejan
 * como están a propósito — no hay dato del que deducir su canal.
 *
 * Idempotente: re-ejecutarla no duplica canales ni cambia nada ya asignado.
 */
class BackfillMarketplaceChannelOnOrders extends Migration
{
    public function up()
    {
        $schema = Schema::connection('tenant');

        // Tenants sin el módulo de marketplace externo no tienen nada que hacer.
        if (!$schema->hasTable('marketplace_channels')
            || !$schema->hasTable('marketplace_orders')
            || !$schema->hasTable('sales_channels')
            || !$schema->hasColumn('orders', 'channel_id')) {
            return;
        }

        $db = DB::connection('tenant');

        $warehouseId = $schema->hasTable('warehouses')
            ? $db->table('warehouses')->value('id')
            : null;

        foreach ($db->table('marketplace_channels')->get() as $externo) {
            $platform = strtolower(trim((string) ($externo->platform ?? '')));
            if ($platform === '') {
                continue;
            }

            // La MISMA funcion que usa el alta en vivo. Duplicar la formula aqui
            // haria que backfill y runtime crearan canales distintos para la
            // misma tienda, partiendo el reporte de ventas en dos.
            $code = \App\Models\Tenant\SalesChannel::platformCode($platform);

            $salesChannelId = $db->table('sales_channels')->where('code', $code)->value('id');

            if (!$salesChannelId) {
                // Reutilizar uno creado a mano con nombre reconocible antes de
                // fabricar otro: dos canales para la misma tienda partirían el
                // reporte de ventas en dos.
                $salesChannelId = $db->table('sales_channels')
                    ->where('type', 'marketplace')
                    ->where('name', 'LIKE', '%' . $platform . '%')
                    ->value('id');
            }

            if (!$salesChannelId) {
                $salesChannelId = $db->table('sales_channels')->insertGetId([
                    'name'         => substr($externo->name ?: ucfirst($platform), 0, 60),
                    'type'         => 'marketplace',
                    'code'         => $code,
                    'warehouse_id' => $warehouseId,
                    'is_active'    => false,
                    'settings'     => json_encode([
                        'icon'     => '🛍️',
                        'color'    => '#0ea5e9',
                        'platform' => $platform,
                    ]),
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ]);
            }

            // Los pedidos ERP de ESTE canal externo que se quedaron sin canal.
            $pedidos = $db->table('marketplace_orders')
                          ->where('channel_id', $externo->id)
                          ->whereNotNull('order_id')
                          ->pluck('order_id');

            foreach ($pedidos->chunk(500) as $lote) {
                $db->table('orders')
                   ->whereIn('id', $lote->all())
                   ->whereNull('channel_id')
                   ->update(['channel_id' => $salesChannelId]);
            }
        }
    }

    /**
     * No revierte el backfill: dejar los pedidos otra vez sin canal solo
     * reproduciría el bug. Los canales creados se quedan — son inofensivos,
     * están inactivos y borrarlos rompería el `channel_id` de los pedidos.
     */
    public function down()
    {
        //
    }
}
