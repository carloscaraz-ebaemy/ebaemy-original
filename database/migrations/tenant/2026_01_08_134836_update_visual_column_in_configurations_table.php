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
    public function up(): void
    {
        DB::table('configurations')->get()->each(function ($config) {
            $visual = json_decode($config->visual, true);

            if (!array_key_exists('sidebar_margin', $visual)) {
                $visual['sidebar_margin'] = true;
            }

            DB::table('configurations')
                ->where('id', $config->id)
                ->update([
                    'visual' => json_encode($visual),
                ]);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        DB::table('configurations')->get()->each(function ($config) {
            $visual = json_decode($config->visual, true);

            if (array_key_exists('sidebar_margin', $visual)) {
                unset($visual['sidebar_margin']);
            }

            DB::table('configurations')
                ->where('id', $config->id)
                ->update([
                    'visual' => json_encode($visual),
                ]);
        });
    }
};
