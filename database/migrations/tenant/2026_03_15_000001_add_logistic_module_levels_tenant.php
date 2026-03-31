<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddLogisticModuleLevelsTenant extends Migration
{
    public function up()
    {
        // Buscar o crear módulo logístico dinámicamente
        $moduleId = DB::table('modules')->where('value', 'logistic')->value('id');
        if (!$moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'value' => 'logistic',
                'description' => 'Módulo Logístico',
                'order_menu' => 99,
            ]);
        }

        $levels = [
            ['value' => 'logistic_queue',    'description' => 'Cola de Despacho',  'route_name' => 'logistic.sale_notes.queue',   'route_path' => '/logistic/sale-notes/queue',   'label_menu' => 'CD'],
            ['value' => 'logistic_history',  'description' => 'Historial',         'route_name' => 'logistic.sale_notes.history', 'route_path' => '/logistic/sale-notes/history', 'label_menu' => 'HIS'],
            ['value' => 'logistic_couriers', 'description' => 'Couriers',          'route_name' => 'logistic.couriers.index',     'route_path' => '/logistic/couriers',           'label_menu' => 'COU'],
            ['value' => 'logistic_tracking', 'description' => 'Tracking cliente',  'route_name' => 'logistic.tracking',           'route_path' => '/logistic/tracking',           'label_menu' => 'TRK'],
            ['value' => 'logistic_returns',  'description' => 'Devoluciones',      'route_name' => 'logistic.returns.index',      'route_path' => '/logistic/returns',            'label_menu' => 'DEV'],
        ];

        $existing = DB::table('module_levels')->where('module_id', $moduleId)->pluck('value')->toArray();

        foreach ($levels as $level) {
            if (!in_array($level['value'], $existing)) {
                DB::table('module_levels')->insert(array_merge($level, ['module_id' => $moduleId]));
            }
        }
    }

    public function down()
    {
        DB::table('module_levels')->whereIn('id', [100, 101, 102, 103, 104])->delete();
    }
}
