<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddLogisticModuleLevels extends Migration
{
    // Columnas disponibles en la tabla system module_levels:
    // id, value, description, module_id

    public function up()
    {
        // Crear módulo logístico si no existe
        $moduleId = DB::table('modules')->where('value', 'logistic')->value('id');
        if (!$moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'value' => 'logistic',
                'description' => 'Módulo Logístico',
            ]);
        }

        // Insertar niveles solo si no existen
        $existing = DB::table('module_levels')->where('module_id', $moduleId)->pluck('value')->toArray();
        $levels = [
            ['value' => 'logistic_queue',    'description' => 'Cola de Despacho'],
            ['value' => 'logistic_history',  'description' => 'Historial'],
            ['value' => 'logistic_couriers', 'description' => 'Couriers'],
            ['value' => 'logistic_tracking', 'description' => 'Tracking cliente'],
            ['value' => 'logistic_returns',  'description' => 'Devoluciones'],
        ];

        foreach ($levels as $level) {
            if (!in_array($level['value'], $existing)) {
                DB::table('module_levels')->insert([
                    'module_id'   => $moduleId,
                    'value'       => $level['value'],
                    'description' => $level['description'],
                ]);
            }
        }
    }

    public function down()
    {
        DB::table('module_levels')->whereIn('id', [100, 101, 102, 103, 104])->delete();
    }
}
