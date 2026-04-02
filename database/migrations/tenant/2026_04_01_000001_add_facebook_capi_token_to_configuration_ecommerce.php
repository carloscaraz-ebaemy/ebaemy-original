<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFacebookCapiTokenToConfigurationEcommerce extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'facebook_capi_token'))
                $table->text('facebook_capi_token')->nullable()->after('facebook_pixel_id');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (Schema::hasColumn('configuration_ecommerce', 'facebook_capi_token'))
                $table->dropColumn('facebook_capi_token');
        });
    }
}
