<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega permisos RBAC del módulo WhatsApp y los asigna a los roles
 * super-admin y admin del tenant. Seguro de correr múltiples veces
 * (insertOrIgnore). Si no hay tabla `permissions` aún, sale sin hacer nada.
 *
 * Permisos creados:
 *   - whatsapp.view       → ver panel y dashboard
 *   - whatsapp.config     → editar credenciales y preferencias
 *   - whatsapp.send_test  → disparar envíos de prueba desde el panel
 *
 * Backward-compat:
 *   - El middleware CheckPermission permite el paso a users con
 *     type='admin' aunque NO tengan el permiso explícito, y a los que
 *     tengan rol super-admin. Así ningún admin actual queda sin acceso.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions') || !Schema::hasTable('roles') || !Schema::hasTable('role_permission')) {
            // RBAC no instalado en este tenant — no-op (backward compat)
            return;
        }

        $now = now();
        $permissions = [
            'whatsapp.view'      => 'WhatsApp - Ver panel y métricas',
            'whatsapp.config'    => 'WhatsApp - Configurar credenciales y preferencias',
            'whatsapp.send_test' => 'WhatsApp - Enviar mensajes de prueba',
        ];

        foreach ($permissions as $key => $label) {
            [$module, $action] = explode('.', $key);
            DB::table('permissions')->insertOrIgnore([
                'module'       => $module,
                'action'       => $action,
                'key'          => $key,
                'display_name' => $label,
                'created_at'   => $now,
                'updated_at'   => $now,
            ]);
        }

        // Asignar a super-admin y admin
        $permIds = DB::table('permissions')
            ->whereIn('key', array_keys($permissions))
            ->pluck('id');

        $adminRoles = DB::table('roles')
            ->whereIn('name', ['super-admin', 'admin'])
            ->pluck('id');

        foreach ($adminRoles as $roleId) {
            foreach ($permIds as $permId) {
                DB::table('role_permission')->insertOrIgnore([
                    'role_id'       => $roleId,
                    'permission_id' => $permId,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('permissions')) return;

        DB::table('permissions')
            ->whereIn('key', ['whatsapp.view', 'whatsapp.config', 'whatsapp.send_test'])
            ->delete();
    }
};
