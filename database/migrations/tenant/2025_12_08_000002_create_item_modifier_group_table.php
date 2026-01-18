<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateItemModifierGroupTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('item_modifier_group')) {
            Schema::create('item_modifier_group', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('item_id');
                $table->unsignedBigInteger('modifier_group_id');
                $table->boolean('default_open')->default(false);
                $table->timestamps();

                $table->foreign('item_id')->references('id')->on('items');
                $table->foreign('modifier_group_id')->references('id')->on('modifier_groups');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('item_modifier_group');
    }
}
