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
    public function up()
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->string('original_environment', 100)
                ->nullable()
                ->after('environment')
                ->comment('Ambiente original de la mesa (NULL si no ha sido movida)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('restaurant_tables', function (Blueprint $table) {
            $table->dropColumn('original_environment');
        });
    }
};
