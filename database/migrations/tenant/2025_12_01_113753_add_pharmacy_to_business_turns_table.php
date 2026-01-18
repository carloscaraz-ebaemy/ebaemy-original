<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $exists = DB::table('business_turns')
            ->where('value', 'pharmacy')
            ->exists();

        if (!$exists) {
            DB::table('business_turns')->insert([
                'value' => 'pharmacy',
                'name' => 'Farmacia',
                'active' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('business_turns')->where('value', 'pharmacy')->delete();
    }
};
