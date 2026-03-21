<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('courier_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Semilla con couriers comunes en Perú
        $couriers = [
            ['name' => 'Olva Courier',       'sort_order' => 1],
            ['name' => 'Shalom',             'sort_order' => 2],
            ['name' => 'GW Yichang',         'sort_order' => 3],
            ['name' => 'Cruz del Sur',       'sort_order' => 4],
            ['name' => 'Inka Express',       'sort_order' => 5],
            ['name' => 'Civa',               'sort_order' => 6],
            ['name' => 'Flores',             'sort_order' => 7],
            ['name' => 'JET Cargo',          'sort_order' => 8],
            ['name' => 'Motorizado propio',  'sort_order' => 9],
        ];

        $now = now();
        foreach ($couriers as &$c) {
            $c['is_active']   = true;
            $c['created_at']  = $now;
            $c['updated_at']  = $now;
        }

        DB::connection('tenant')->table('courier_companies')->insert($couriers);
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('courier_companies');
    }
};
