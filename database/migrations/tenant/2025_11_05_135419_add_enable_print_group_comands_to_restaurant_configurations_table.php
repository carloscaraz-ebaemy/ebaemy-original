<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('restaurant_configurations', function (Blueprint $table) {
            $table->tinyInteger('enabled_print_group_commands')
                  ->default(0)
                  ->after('enabled_print_command');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('restaurant_configurations', function (Blueprint $table) {
            $table->dropColumn('enabled_print_group_commands');
        });
    }
};
