<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnClientNameAndPhonewsToClients extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('client_name')->after('name')->nullable();
            $table->string('phone_ws', 20)->after('email')->nullable();
            $table->string('contact_email')->after('phone_ws')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('client_name');
            $table->dropColumn('phone_ws');
            $table->dropColumn('contact_email');
        });
    }
}
