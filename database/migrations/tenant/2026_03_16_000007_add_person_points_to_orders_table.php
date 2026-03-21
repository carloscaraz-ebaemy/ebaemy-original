<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPersonPointsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('person_id')->nullable()->after('id')
                  ->comment('Persona (cliente ecommerce) que realizó el pedido');
            $table->decimal('points_redeemed', 10, 2)->default(0)->after('total')
                  ->comment('Puntos canjeados como descuento en este pedido');
            $table->decimal('points_earned', 10, 2)->default(0)->after('points_redeemed')
                  ->comment('Puntos otorgados por este pedido');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['person_id', 'points_redeemed', 'points_earned']);
        });
    }
}
