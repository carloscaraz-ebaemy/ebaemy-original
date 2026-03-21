<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddLogisticModuleLevelsTenant extends Migration
{
    public function up()
    {
        DB::table('module_levels')->insert([
            [
                'id'          => 100,
                'module_id'   => 53,
                'value'       => 'logistic_queue',
                'description' => 'Cola de Despacho',
                'route_name'  => 'logistic.sale_notes.queue',
                'route_path'  => '/logistic/sale-notes/queue',
                'label_menu'  => 'CD',
            ],
            [
                'id'          => 101,
                'module_id'   => 53,
                'value'       => 'logistic_history',
                'description' => 'Historial',
                'route_name'  => 'logistic.sale_notes.history',
                'route_path'  => '/logistic/sale-notes/history',
                'label_menu'  => 'HIS',
            ],
            [
                'id'          => 102,
                'module_id'   => 53,
                'value'       => 'logistic_couriers',
                'description' => 'Couriers',
                'route_name'  => 'logistic.couriers.index',
                'route_path'  => '/logistic/couriers',
                'label_menu'  => 'COU',
            ],
            [
                'id'          => 103,
                'module_id'   => 53,
                'value'       => 'logistic_tracking',
                'description' => 'Tracking cliente',
                'route_name'  => 'logistic.tracking',
                'route_path'  => '/logistic/tracking',
                'label_menu'  => 'TRK',
            ],
            [
                'id'          => 104,
                'module_id'   => 53,
                'value'       => 'logistic_returns',
                'description' => 'Devoluciones',
                'route_name'  => 'logistic.returns.index',
                'route_path'  => '/logistic/returns',
                'label_menu'  => 'DEV',
            ],
        ]);
    }

    public function down()
    {
        DB::table('module_levels')->whereIn('id', [100, 101, 102, 103, 104])->delete();
    }
}
