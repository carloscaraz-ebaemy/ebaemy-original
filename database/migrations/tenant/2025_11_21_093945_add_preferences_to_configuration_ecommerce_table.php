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
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('customised_link_three');
        });

        DB::table('configuration_ecommerce')->update([
            'preferences' => json_encode([
                'show_description' => 1,
                'show_stock' => 0,
                'only_available_products' => 0,
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
