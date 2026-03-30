<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddCanceladoToStatusOrders extends Migration
{
    public function up()
    {
        DB::table('status_orders')->insertOrIgnore([
            ['id' => 5, 'description' => 'Cancelado', 'created_at' => now()],
        ]);
    }

    public function down()
    {
        DB::table('status_orders')->where('id', 5)->delete();
    }
}
