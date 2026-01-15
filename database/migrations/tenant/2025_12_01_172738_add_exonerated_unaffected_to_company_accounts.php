<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExoneratedUnaffectedToCompanyAccounts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('company_accounts', function (Blueprint $table) {
            $table->integer('exonerated')->nullable()->after('igv_usd');
            $table->integer('unaffected')->nullable()->after('exonerated');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('company_accounts', function (Blueprint $table) {
            $table->dropColumn(['exonerated', 'unaffected']);
        });
    }
}
