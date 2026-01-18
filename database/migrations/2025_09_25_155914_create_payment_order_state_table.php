<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreatePaymentOrderStateTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_order_states', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
        });

        DB::table('payment_order_states')->insert([
            ['name' => 'Pendiente'],
            ['name' => 'Pagado'],
            ['name' => 'Vencido'],
            ['name' => 'Anulado'],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_order_states');
    }
}
