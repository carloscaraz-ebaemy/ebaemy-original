<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddBannerUrlToPromotionsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('promotions')) return;

        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'banner_url'))
                $table->string('banner_url')->nullable()->after('spot_url');
        });
    }

    public function down()
    {
        if (!Schema::hasTable('promotions')) return;

        Schema::table('promotions', function (Blueprint $table) {
            if (Schema::hasColumn('promotions', 'banner_url'))
                $table->dropColumn('banner_url');
        });
    }
}
