<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = 'tenant';
        
        // 1. Agregar group_id si no existe
        if (!Schema::connection($connection)->hasColumn('restaurant_tables', 'group_id')) {
            Schema::connection($connection)->table('restaurant_tables', function (Blueprint $table) {
                $table->foreignId('group_id')->nullable();
            });
        }

        // 2. Verificar y agregar foreign keys
        $this->addForeignKeyIfNotExists(
            $connection,
            'restaurant_tables',
            'group_id',
            'restaurant_table_groups',
            'id'
        );

        $this->addForeignKeyIfNotExists(
            $connection,
            'restaurant_table_groups',
            'main_table_id',
            'restaurant_tables',
            'id'
        );
    }

    private function addForeignKeyIfNotExists($connection, $table, $column, $refTable, $refColumn)
    {
        $foreignKeys = Schema::connection($connection)
            ->getConnection()
            ->getDoctrineSchemaManager()
            ->listTableForeignKeys($table);

        $exists = false;
        foreach ($foreignKeys as $fk) {
            if (in_array($column, $fk->getLocalColumns())) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            Schema::connection($connection)->table($table, function (Blueprint $table) use ($column, $refTable, $refColumn) {
                $table->foreign($column)
                      ->references($refColumn)
                      ->on($refTable)
                      ->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('restaurant_table_groups', function (Blueprint $table) {
            $table->dropForeign(['main_table_id']);
        });

        Schema::connection('tenant')->table('restaurant_tables', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
