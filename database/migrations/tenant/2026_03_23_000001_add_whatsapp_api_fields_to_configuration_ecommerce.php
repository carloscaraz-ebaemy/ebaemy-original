<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWhatsappApiFieldsToConfigurationEcommerce extends Migration
{
    public function up()
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'whatsapp_api_token')) {
                $table->text('whatsapp_api_token')->nullable()->after('phone_whatsapp')
                      ->comment('Meta Cloud API bearer token (system-user token)');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'whatsapp_phone_id')) {
                $table->string('whatsapp_phone_id')->nullable()->after('whatsapp_api_token')
                      ->comment('Meta Phone Number ID from developer console');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'whatsapp_vendor_number')) {
                $table->string('whatsapp_vendor_number')->nullable()->after('whatsapp_phone_id')
                      ->comment('Vendor WhatsApp number to receive new-order alerts (with country code, e.g. 51987654321)');
            }
        });
    }

    public function down()
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_api_token', 'whatsapp_phone_id', 'whatsapp_vendor_number']);
        });
    }
}
