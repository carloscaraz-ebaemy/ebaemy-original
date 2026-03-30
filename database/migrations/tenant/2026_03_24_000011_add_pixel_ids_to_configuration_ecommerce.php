<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPixelIdsToConfigurationEcommerce extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'facebook_pixel_id'))
                $table->string('facebook_pixel_id')->nullable();
            if (!Schema::hasColumn('configuration_ecommerce', 'tiktok_pixel_id'))
                $table->string('tiktok_pixel_id')->nullable();
            if (!Schema::hasColumn('configuration_ecommerce', 'ga4_measurement_id'))
                $table->string('ga4_measurement_id')->nullable();
        });
    }

    public function down()
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $cols = ['facebook_pixel_id', 'tiktok_pixel_id', 'ga4_measurement_id'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('configuration_ecommerce', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
}
