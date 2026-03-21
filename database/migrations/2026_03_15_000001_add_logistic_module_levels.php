<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddLogisticModuleLevels extends Migration
{
    // Columnas disponibles en la tabla system module_levels:
    // id, value, description, module_id

    public function up()
    {
        DB::table('module_levels')->insert([
            ['id' => 100, 'module_id' => 53, 'value' => 'logistic_queue',    'description' => 'Cola de Despacho'],
            ['id' => 101, 'module_id' => 53, 'value' => 'logistic_history',  'description' => 'Historial'],
            ['id' => 102, 'module_id' => 53, 'value' => 'logistic_couriers', 'description' => 'Couriers'],
            ['id' => 103, 'module_id' => 53, 'value' => 'logistic_tracking', 'description' => 'Tracking cliente'],
            ['id' => 104, 'module_id' => 53, 'value' => 'logistic_returns',  'description' => 'Devoluciones'],
        ]);
    }

    public function down()
    {
        DB::table('module_levels')->whereIn('id', [100, 101, 102, 103, 104])->delete();
    }
}
