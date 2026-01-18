<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table
                ->boolean('is_dish')
                ->default(false)
                ->after('status');
        });

        /**
         * Un item es plato si tiene supplies asociados
         */
        DB::statement("
            UPDATE items i
            SET i.is_dish = 1
            WHERE EXISTS (
                SELECT 1
                FROM restaurant_item_supplies ris
                WHERE ris.item_id = i.id
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('is_dish');
        });
    }
};
