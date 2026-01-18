<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnCronToConfigurations extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->boolean('active_cron')->default(false);
            $table->boolean('send_notification_cron')->default(false);
            $table->integer('day_before_due')->default(3);
            $table->time('hour_generate_payment_order')->default('09:00:00');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn('active_cron');
            $table->dropColumn('day_before_due');
            $table->dropColumn('send_notification_cron');
            $table->dropColumn('hour_generate_payment_order');
        });
    }
}
