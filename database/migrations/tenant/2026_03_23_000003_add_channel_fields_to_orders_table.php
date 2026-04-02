<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega dimensiones de canal de venta a la tabla orders.
 *
 * channel_id   → FK a sales_channels (nullable para compatibilidad con pedidos históricos)
 * warehouse_id → almacén desde donde se despachará este pedido
 * seller_id    → vendedor asignado (null para canales digitales/automatizados)
 *
 * Compatibilidad: todos los campos son nullable → pedidos anteriores no se ven afectados.
 */
class AddChannelFieldsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'channel_id')) {
                $table->unsignedInteger('channel_id')->nullable()->after('status_order_id')
                      ->comment('Canal de venta: ecommerce, pos, whatsapp, etc.');
            }
            if (!Schema::hasColumn('orders', 'warehouse_id')) {
                $table->unsignedInteger('warehouse_id')->nullable()->after('channel_id')
                      ->comment('Almacén asignado para despacho de este pedido');
            }
            if (!Schema::hasColumn('orders', 'seller_id')) {
                $table->unsignedInteger('seller_id')->nullable()->after('warehouse_id')
                      ->comment('Vendedor responsable — null para canales digitales');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['channel_id', 'warehouse_id', 'seller_id']);
        });
    }
}
