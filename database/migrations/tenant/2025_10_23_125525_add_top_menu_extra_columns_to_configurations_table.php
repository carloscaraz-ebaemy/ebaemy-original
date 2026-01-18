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
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->json('top_menu_extra_one')->nullable()->after('top_menu_d_id');
            $table->json('top_menu_extra_two')->nullable()->after('top_menu_extra_one');
        });

        DB::table('configurations')->update([
            'top_menu_extra_one' => json_encode(['link' => '', 'initials' => '']),
            'top_menu_extra_two' => json_encode(['link' => '', 'initials' => '']),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['top_menu_extra_one', 'top_menu_extra_two']);
        });
    }
};
