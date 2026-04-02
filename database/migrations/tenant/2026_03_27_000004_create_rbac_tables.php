<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->unique();
                $table->string('display_name', 120);
                $table->string('description', 255)->nullable();
                $table->boolean('is_system')->default(false); // system roles can't be deleted
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->id();
                $table->string('module', 80); // inventory, sales, ecommerce, config, logistic
                $table->string('action', 50); // view, create, update, delete, export, approve
                $table->string('key')->unique(); // inventory.view, sales.create, etc.
                $table->string('display_name', 150);
                $table->timestamps();

                $table->index('module');
            });
        }

        if (!Schema::hasTable('role_permission')) {
            Schema::create('role_permission', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('permission_id');
                $table->primary(['role_id', 'permission_id']);
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('user_role')) {
            Schema::create('user_role', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('establishment_id')->nullable(); // scope role to establishment
                $table->primary(['user_id', 'role_id']);
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->index('establishment_id');
            });
        }

        // Seed default roles (idempotent via insertOrIgnore on unique `name`)
        $now = now();
        $roles = [
            ['name' => 'super-admin', 'display_name' => 'Super Administrador', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'admin', 'display_name' => 'Administrador', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'seller', 'display_name' => 'Vendedor', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'warehouse', 'display_name' => 'Almacenero', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'cashier', 'display_name' => 'Cajero', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'accountant', 'display_name' => 'Contador', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        \DB::table('roles')->insertOrIgnore($roles);

        // Seed default permissions (idempotent via insertOrIgnore on unique `key`)
        $modules = [
            'dashboard' => ['view'],
            'documents' => ['view', 'create', 'update', 'delete', 'export', 'send-sunat'],
            'sale-notes' => ['view', 'create', 'update', 'delete', 'export'],
            'orders' => ['view', 'create', 'update', 'delete', 'verify', 'dispatch'],
            'purchases' => ['view', 'create', 'update', 'delete', 'export'],
            'inventory' => ['view', 'create', 'transfer', 'adjust', 'export'],
            'items' => ['view', 'create', 'update', 'delete', 'export', 'import'],
            'persons' => ['view', 'create', 'update', 'delete', 'export'],
            'cash' => ['view', 'open', 'close', 'report'],
            'pos' => ['view', 'sell'],
            'reports' => ['view', 'export'],
            'finance' => ['view', 'create', 'export'],
            'config' => ['view', 'update'],
            'users' => ['view', 'create', 'update', 'delete'],
            'ecommerce' => ['view', 'config', 'products', 'orders', 'promotions'],
            'logistic' => ['view', 'dispatch', 'returns', 'couriers'],
            'restaurant' => ['view', 'tables', 'menu', 'orders'],
        ];

        $permRows = [];
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $permRows[] = [
                    'module' => $module,
                    'action' => $action,
                    'key' => "{$module}.{$action}",
                    'display_name' => ucfirst($module) . ' - ' . ucfirst(str_replace('-', ' ', $action)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        \DB::table('permissions')->insertOrIgnore($permRows);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
