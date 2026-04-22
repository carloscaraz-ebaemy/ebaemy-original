<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega retry_count a marketplace_leads para que el command
 * marketplace:retry-failed-leads pueda aplicar backoff y auto-archivo
 * cuando un lead supera max-attempts.
 */
class AddRetryCountToMarketplaceLeadsTable extends Migration
{
    public function up()
    {
        Schema::table('marketplace_leads', function (Blueprint $table) {
            $table->unsignedTinyInteger('retry_count')->default(0)->after('sync_error');
        });
    }

    public function down()
    {
        Schema::table('marketplace_leads', function (Blueprint $table) {
            $table->dropColumn('retry_count');
        });
    }
}
