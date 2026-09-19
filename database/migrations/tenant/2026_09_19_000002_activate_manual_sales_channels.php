<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reactiva los canales que existen PARA cargar pedidos a mano.
 *
 * El alta manual ofrecía únicamente los canales automáticos —Tienda Online,
 * Marketplace, Saga— porque son los únicos que quedaron activos, y esos son
 * justamente los que no se pueden elegir: sus pedidos los crea el checkout o
 * los trae la API del canal con su propio número. El desplegable acababa
 * ofreciendo tres opciones equivocadas y ninguna correcta.
 *
 * Punto de Venta, WhatsApp y Ventas Telefónicas se sembraron en marzo (dos de
 * ellos ya apagados «por defecto») y nunca se encendieron, así que el operador
 * no tenía forma de decir por dónde entró el pedido que está escribiendo.
 *
 * Sólo toca `is_active`, y sólo de esos tres códigos: no crea canales, no
 * renombra y no desactiva nada. Un tenant que los haya apagado a propósito los
 * verá volver, que es el precio de que el alta manual sea usable; apagarlos
 * otra vez sigue siendo un clic en su configuración.
 */
return new class extends Migration
{
    /** Los del seed de marzo. El de Envíos (ENV01) ya se activa solo. */
    private const CODIGOS = ['POS01', 'WHA01', 'TEL01'];

    public function up(): void
    {
        $schema = Schema::connection('tenant');

        if (!$schema->hasTable('sales_channels')) {
            return;
        }

        $db = DB::connection('tenant');

        $db->table('sales_channels')
           ->whereIn('code', self::CODIGOS)
           ->where('is_active', false)
           ->update(['is_active' => true, 'updated_at' => now()]);

        // Sin almacén, el alta manual no sabe de dónde descontar el stock y la
        // reserva se queda sin sitio concreto. Se completa sólo donde está
        // vacío: un canal que ya apunta a un almacén se respeta.
        if (!$schema->hasTable('warehouses')) {
            return;
        }

        $warehouseId = $db->table('warehouses')->value('id');

        if ($warehouseId) {
            $db->table('sales_channels')
               ->whereIn('code', self::CODIGOS)
               ->whereNull('warehouse_id')
               ->update(['warehouse_id' => $warehouseId, 'updated_at' => now()]);
        }
    }

    /**
     * No revierte: volver a apagarlos dejaría el alta manual como estaba, sin
     * un canal que se pueda elegir. Es el bug que esto arregla.
     */
    public function down(): void
    {
        //
    }
};
