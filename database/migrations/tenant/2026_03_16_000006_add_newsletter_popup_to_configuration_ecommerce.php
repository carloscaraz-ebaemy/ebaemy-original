<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNewsletterPopupToConfigurationEcommerce extends Migration
{
    public function up()
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->boolean('newsletter_popup_enabled')->default(false)->after('google_login_enabled');
            $table->string('newsletter_popup_title')->nullable()->after('newsletter_popup_enabled');
            $table->string('newsletter_popup_desc')->nullable()->after('newsletter_popup_title');
            $table->string('newsletter_discount_code')->nullable()->after('newsletter_popup_desc');
            $table->string('newsletter_popup_image')->nullable()->after('newsletter_discount_code');
        });
    }

    public function down()
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->dropColumn([
                'newsletter_popup_enabled',
                'newsletter_popup_title',
                'newsletter_popup_desc',
                'newsletter_discount_code',
                'newsletter_popup_image',
            ]);
        });
    }
}
