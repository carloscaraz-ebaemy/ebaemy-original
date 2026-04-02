<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleOauthToConfigurationEcommerce extends Migration
{
    public function up()
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'google_client_id')) {
                $table->string('google_client_id')->nullable()->after('google_site_verification');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'google_client_secret')) {
                $table->string('google_client_secret')->nullable()->after('google_client_id');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'google_login_enabled')) {
                $table->boolean('google_login_enabled')->default(false)->after('google_client_secret');
            }
        });
    }

    public function down()
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->dropColumn(['google_client_id', 'google_client_secret', 'google_login_enabled']);
        });
    }
}
