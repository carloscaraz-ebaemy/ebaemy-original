<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });
        
        DB::statement('ALTER TABLE `promotions` MODIFY `item_id` INT UNSIGNED NULL');
        
        Schema::table('promotions', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });
        
        DB::statement('ALTER TABLE `promotions` MODIFY `item_id` INT UNSIGNED NOT NULL');
        
        Schema::table('promotions', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
        });
    }
};
