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
        $configurations = DB::table('configurations')->get();

        foreach ($configurations as $config) {
            $login = json_decode($config->login, true);

            if (is_array($login) && !isset($login['padding_in_form'])) {
                $newLogin = [];

                foreach ($login as $key => $value) {
                    $newLogin[$key] = $value;

                    if ($key === 'position_logo') {
                        $newLogin['padding_in_form'] = false;
                    }
                }

                DB::table('configurations')
                    ->where('id', $config->id)
                    ->update(['login' => json_encode($newLogin, JSON_UNESCAPED_SLASHES)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        $configurations = DB::table('configurations')->get();

        foreach ($configurations as $config) {
            $login = json_decode($config->login, true);

            if (is_array($login) && isset($login['padding_in_form'])) {
                unset($login['padding_in_form']);

                DB::table('configurations')
                    ->where('id', $config->id)
                    ->update(['login' => json_encode($login, JSON_UNESCAPED_SLASHES)]);
            }
        }
    }
};
