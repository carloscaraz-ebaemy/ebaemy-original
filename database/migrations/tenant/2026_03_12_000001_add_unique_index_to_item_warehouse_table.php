<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUniqueIndexToItemWarehouseTable extends Migration
{
    public function up()
    {
        Schema::table('item_warehouse', function (Blueprint $table) {
            // Primero eliminar duplicados si existen (keep el de mayor stock)
            \DB::statement("
                DELETE iw1 FROM item_warehouse iw1
                INNER JOIN item_warehouse iw2
                ON iw1.item_id = iw2.item_id
                AND iw1.warehouse_id = iw2.warehouse_id
                AND iw1.id > iw2.id
            ");

            // Agregar índice único para evitar stock duplicado por producto/almacén
            $table->unique(['item_id', 'warehouse_id'], 'item_warehouse_item_id_warehouse_id_unique');
        });
    }

    public function down()
    {
        Schema::table('item_warehouse', function (Blueprint $table) {
            $table->dropUnique('item_warehouse_item_id_warehouse_id_unique');
        });
    }
}
