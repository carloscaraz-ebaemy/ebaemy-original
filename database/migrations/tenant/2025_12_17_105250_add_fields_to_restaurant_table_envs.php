<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('restaurant_table_envs', function (Blueprint $table) {
            $table->boolean('is_delivery')->default(false)->commit('es un ambiente de delivery');
            $table->boolean('is_takeaway')->default(false)->commit('es un ambiente de para llevar');
            $table->boolean('can_edit')->default(true)->commit('pueden editar cantidad de mesas y nombre');
            $table->boolean('can_deactivate')->default(true)->commit('pueden desactivar el ambiente');
            $table->boolean('can_delete')->default(true);
        });

        DB::table('restaurant_table_envs')->truncate();
        DB::table('restaurant_table_envs')->insert([
            [
                'id' => 1,
                'active' => false,
                'name' => 'Delivery',
                'tables_quantity' => 0,
                'is_delivery' => true,
                'is_takeaway' => false,
                'can_edit' => false,
                'can_deactivate' => true,
                'can_delete' => false,
            ],
            [
                'id' => 2,
                'active' => false,
                'name' => 'Para Llevar',
                'tables_quantity' => 0,
                'is_delivery' => false,
                'is_takeaway' => true,
                'can_edit' => false,
                'can_deactivate' => true,
                'can_delete' => false,
            ],
            [
                'id' => 3,
                'active' => true,
                'name' => 'Ambiente 1',
                'tables_quantity' => 25,
                'is_delivery' => false,
                'is_takeaway' => false,
                'can_edit' => true,
                'can_deactivate' => false,
                'can_delete' => false,
            ],
            [
                'id' => 4,
                'active' => false,
                'name' => 'Ambiente 2',
                'tables_quantity' => 25,
                'is_delivery' => false,
                'is_takeaway' => false,
                'can_edit' => true,
                'can_deactivate' => true,
                'can_delete' => true,
            ],
        ]);


    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('restaurant_table_envs', function (Blueprint $table) {
            $table->dropColumn('is_delivery');
            $table->dropColumn('is_takeaway');
            $table->dropColumn('can_delete');
        });
    }
};
