<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega el canal de venta "Marketplace" a cada tenant para que los pedidos
 * provenientes de ebaemy.com queden trazados con channel_id correspondiente.
 * Desactivado por defecto — el tenant lo activa cuando publica productos.
 */
class SeedMarketplaceSalesChannel extends Migration
{
    public function up()
    {
        if (!Schema::connection('tenant')->hasTable('sales_channels')) return;

        $defaultWarehouseId = DB::connection('tenant')->table('warehouses')->value('id');

        if (DB::connection('tenant')->table('sales_channels')->where('code', 'MKP01')->exists()) {
            return;
        }

        DB::connection('tenant')->table('sales_channels')->insert([
            'name'         => 'Marketplace ebaemy',
            'type'         => 'marketplace',
            'code'         => 'MKP01',
            'warehouse_id' => $defaultWarehouseId,
            'is_active'    => false, // se activa cuando el tenant publica su primer item
            'settings'     => json_encode([
                'icon'  => '🌐',
                'color' => '#8b5cf6',
                'description' => 'Pedidos provenientes del marketplace central (ebaemy.com)',
            ]),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down()
    {
        if (!Schema::connection('tenant')->hasTable('sales_channels')) return;
        DB::connection('tenant')->table('sales_channels')->where('code', 'MKP01')->delete();
    }
}
