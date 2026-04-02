<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixItemSetsForeignKeyCascade extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('item_sets')) return;

        // Clean orphan records where individual_item was deleted
        DB::statement('DELETE item_sets FROM item_sets LEFT JOIN items ON item_sets.individual_item_id = items.id WHERE items.id IS NULL');

        // Drop old foreign key and recreate with cascade
        Schema::table('item_sets', function (Blueprint $table) {
            try {
                $table->dropForeign(['individual_item_id']);
            } catch (\Throwable $e) {
                // FK may not exist in some environments
            }
        });

        Schema::table('item_sets', function (Blueprint $table) {
            $table->foreign('individual_item_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        // No revert needed
    }
}
