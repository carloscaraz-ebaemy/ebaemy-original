<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnPriceAndPeriodsToClients extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedInteger('plan_period_id')->default(1)->after('plan_id'); // Mensual
			$table->foreign('plan_period_id')->references('id')->on('plan_periods');
            $table->float('price')->nullable()->default(null)->after('plan_period_id');
            $table->date('ending_billing_cycle')->nullable()->default(null)->after('start_billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('price');
            $table->dropColumn('ending_billing_cycle');
            $table->dropForeign(['plan_period_id']);
            $table->dropColumn('plan_period_id');
        });
    }
}
