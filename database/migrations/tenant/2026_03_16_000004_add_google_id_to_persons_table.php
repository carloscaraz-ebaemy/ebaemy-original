<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGoogleIdToPersonsTable extends Migration
{
    public function up()
    {
        Schema::table('persons', function (Blueprint $table) {
            if (!Schema::hasColumn('persons', 'google_id')) {
                $table->string('google_id')->nullable()->after('password');
            }
            if (!Schema::hasColumn('persons', 'avatar')) {
                $table->string('avatar')->nullable()->after('google_id');
            }
        });
    }

    public function down()
    {
        Schema::table('persons', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar']);
        });
    }
}
