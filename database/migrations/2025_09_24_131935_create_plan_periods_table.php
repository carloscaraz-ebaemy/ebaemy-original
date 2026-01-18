<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreatePlanPeriodsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('plan_periods', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name'); 
            $table->integer('months');
        });

        DB::table('plan_periods')->insert([
            ['name' => 'Mensual', 'months' => 1],
            ['name' => 'Bimestral', 'months' => 2],
            ['name' => 'Trimestral', 'months' => 3],
            ['name' => 'Semestral', 'months' => 6],
            ['name' => 'Anual', 'months' => 12]
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('plan_periods');
    }
}
