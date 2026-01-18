<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\System\Plan;
use App\Models\System\User;


class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        User::create([
            'name' => 'Administrador',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('123456'),
            'api_token' => Str::random(60),
        ]);


        DB::table('plan_documents')->insert([
            ['id' => 1, 'description' => 'Facturas, boletas, notas de débito y crédito, resúmenes y anulaciones' ],
            ['id' => 2, 'description' => 'Guias de remisión' ],
            ['id' => 3, 'description' => 'Retenciones'],
            ['id' => 4, 'description' => 'Percepciones']
        ]);

        Plan::create([
            'name' => 'Ilimitado',
            'pricing' =>  99,
            'limit_users' => 0,
            'limit_documents' =>  0,
            'plan_documents' => [1,2,3,4],
            'locked' => true
        ]);

    }
}
