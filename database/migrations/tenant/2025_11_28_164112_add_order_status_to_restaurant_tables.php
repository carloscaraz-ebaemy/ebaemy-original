<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->string('order_status')->default('pending')->after('opening_date');
        });
    }

    public function down()
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn('order_status');
        });
    }
};
