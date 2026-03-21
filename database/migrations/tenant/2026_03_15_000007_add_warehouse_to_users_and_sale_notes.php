<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega:
 *  - users.warehouse_id         → almacén asignado al usuario (almacenero)
 *  - sale_notes.warehouse_id    → almacén que atiende el pedido (se asigna al iniciar picking)
 */
return new class extends Migration
{
    public function up(): void
    {
        // warehouse_id en usuarios (nullable: no todos los usuarios son almaceneros)
        if (!Schema::hasColumn('users', 'warehouse_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('establishment_id');
            });
        }

        // warehouse_id en notas de venta (se llena al iniciar preparación)
        if (!Schema::hasColumn('sale_notes', 'warehouse_id')) {
            Schema::table('sale_notes', function (Blueprint $table) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->after('requires_warehouse_dispatch');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('warehouse_id');
        });
        Schema::table('sale_notes', function (Blueprint $table) {
            $table->dropColumn('warehouse_id');
        });
    }
};
