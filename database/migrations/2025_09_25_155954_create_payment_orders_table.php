<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePaymentOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payment_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('order');
            $table->date('date_of_due');
            $table->integer('notifications')->default(0);
            $table->float('amount');
            $table->timestamp('date_of_payment')->nullable();
            $table->timestamp('date_of_notification')->nullable();
            $table->text('description')->nullable();
            $table->string('created_by')->nullable();
            $table->unsignedInteger('order_state_id');
            $table->unsignedInteger('client_id');
            $table->foreign('order_state_id')->references('id')->on('payment_order_states');
            $table->foreign('client_id')->references('id')->on('clients')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payment_orders');
    }
}
