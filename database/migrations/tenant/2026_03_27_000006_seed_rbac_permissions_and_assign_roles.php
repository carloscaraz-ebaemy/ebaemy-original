<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles')) {
            return;
        }

        // Only seed if empty
        if (DB::table('permissions')->count() > 0) {
            return;
        }

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

        $now = now();
        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                DB::table('permissions')->insertOrIgnore([
                    'module' => $module,
                    'action' => $action,
                    'key' => "{$module}.{$action}",
                    'display_name' => ucfirst(str_replace('-', ' ', $module)) . ' - ' . ucfirst(str_replace('-', ' ', $action)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Assign ALL permissions to super-admin and admin roles
        $allPermIds = DB::table('permissions')->pluck('id');
        $adminRoles = DB::table('roles')->whereIn('name', ['super-admin', 'admin'])->pluck('id');

        foreach ($adminRoles as $roleId) {
            foreach ($allPermIds as $permId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permId,
                ]);
            }
        }

        // Assign seller permissions
        $sellerRole = DB::table('roles')->where('name', 'seller')->value('id');
        if ($sellerRole) {
            $sellerPerms = DB::table('permissions')
                ->where(function ($q) {
                    $q->whereIn('module', ['dashboard', 'pos', 'persons'])
                      ->orWhere(function ($q2) {
                          $q2->whereIn('module', ['documents', 'sale-notes', 'orders', 'items'])
                             ->whereIn('action', ['view', 'create']);
                      });
                })
                ->pluck('id');

            foreach ($sellerPerms as $permId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $sellerRole,
                    'permission_id' => $permId,
                ]);
            }
        }

        // Assign warehouse permissions
        $warehouseRole = DB::table('roles')->where('name', 'warehouse')->value('id');
        if ($warehouseRole) {
            $whPerms = DB::table('permissions')
                ->where(function ($q) {
                    $q->where('module', 'dashboard')->where('action', 'view')
                      ->orWhere('module', 'inventory')
                      ->orWhere('module', 'logistic')
                      ->orWhere(function ($q2) {
                          $q2->where('module', 'items')->where('action', 'view');
                      })
                      ->orWhere(function ($q2) {
                          $q2->where('module', 'orders')->whereIn('action', ['view', 'dispatch']);
                      });
                })
                ->pluck('id');

            foreach ($whPerms as $permId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $warehouseRole,
                    'permission_id' => $permId,
                ]);
            }
        }

        // Assign cashier permissions
        $cashierRole = DB::table('roles')->where('name', 'cashier')->value('id');
        if ($cashierRole) {
            $cashierPerms = DB::table('permissions')
                ->where(function ($q) {
                    $q->where('module', 'dashboard')->where('action', 'view')
                      ->orWhere('module', 'cash')
                      ->orWhere('module', 'pos')
                      ->orWhere(function ($q2) {
                          $q2->whereIn('module', ['documents', 'sale-notes'])->where('action', 'view');
                      });
                })
                ->pluck('id');

            foreach ($cashierPerms as $permId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $cashierRole,
                    'permission_id' => $permId,
                ]);
            }
        }

        // Assign accountant permissions
        $accountantRole = DB::table('roles')->where('name', 'accountant')->value('id');
        if ($accountantRole) {
            $accPerms = DB::table('permissions')
                ->where(function ($q) {
                    $q->where('module', 'dashboard')
                      ->orWhere('module', 'reports')
                      ->orWhere('module', 'finance')
                      ->orWhere(function ($q2) {
                          $q2->whereIn('module', ['documents', 'sale-notes', 'purchases'])
                             ->where('action', 'view');
                      });
                })
                ->pluck('id');

            foreach ($accPerms as $permId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id' => $accountantRole,
                    'permission_id' => $permId,
                ]);
            }
        }

        // Assign existing users to roles based on their current 'type'
        $users = DB::table('users')->get(['id', 'type']);
        foreach ($users as $user) {
            $roleName = match ($user->type) {
                'admin' => 'admin',
                'seller' => 'seller',
                default => 'seller', // safe default
            };

            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            if ($roleId) {
                DB::table('user_role')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role_id' => $roleId,
                    'establishment_id' => null,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('user_role')->truncate();
        DB::table('role_permission')->truncate();
        DB::table('permissions')->truncate();
    }
};
