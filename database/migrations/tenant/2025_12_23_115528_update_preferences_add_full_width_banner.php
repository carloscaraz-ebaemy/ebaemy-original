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
        DB::table('configuration_ecommerce')->get()->each(function ($row) {

            $preferences = json_decode($row->preferences, true) ?? [];

            if (!array_key_exists('full_width_banner', $preferences)) {
                $preferences['full_width_banner'] = 0;

                DB::table('configuration_ecommerce')
                    ->where('id', $row->id)
                    ->update([
                        'preferences' => json_encode($preferences),
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('configuration_ecommerce')->get()->each(function ($row) {

            $preferences = json_decode($row->preferences, true) ?? [];

            if (array_key_exists('full_width_banner', $preferences)) {
                unset($preferences['full_width_banner']);

                DB::table('configuration_ecommerce')
                    ->where('id', $row->id)
                    ->update([
                        'preferences' => json_encode($preferences),
                    ]);
            }
        });
    }
};
