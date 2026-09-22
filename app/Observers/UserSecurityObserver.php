<?php

namespace App\Observers;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\User;

/**
 * Deja rastro en `audit_logs` de lo que se le hace a una cuenta de usuario.
 *
 * Es deliberadamente estrecho. Un observador que audite cualquier `update`
 * sobre `users` escribiria una fila en cada login (Laravel refresca el
 * remember_token) y ahogaria la bitacora en ruido. Aqui solo se registran los
 * campos que cambian QUIEN puede hacer QUE: tipo de usuario, estado, ambito y
 * permisos.
 *
 * La contrasena nunca entra en la bitacora: de un cambio de password solo queda
 * el hecho de que ocurrio.
 */
class UserSecurityObserver
{
    /** Campos cuyo cambio importa desde el punto de vista de seguridad. */
    private const SENSITIVE = [
        'type', 'active', 'locked', 'establishment_id', 'warehouse_id',
        'is_multi_user', 'multi_user_id', 'restaurant_role_id', 'zone_id',
        'permission_force_send_by_summary', 'permission_edit_cpe',
        'permission_edit_item_prices', 'create_payment', 'delete_payment',
        'delete_purchase', 'annular_purchase', 'edit_purchase',
        'recreate_documents', 'multiple_default_document_types',
    ];

    public function created(User $user): void
    {
        $this->safely(fn () => AuditLog::record(
            'create',
            'user',
            "Usuario creado: {$user->email}",
            $user,
            null,
            $this->visible($user->getAttributes())
        ));
    }

    public function updated(User $user): void
    {
        $dirty   = $user->getDirty();
        $changed = array_intersect_key($dirty, array_flip(self::SENSITIVE));

        if (array_key_exists('password', $dirty)) {
            // Solo el hecho, nunca el valor ni el hash.
            $this->safely(fn () => AuditLog::record(
                'update',
                'user',
                "Cambio de contrasena: {$user->email}",
                $user
            ));
        }

        if (!$changed) {
            return;
        }

        $this->safely(fn () => AuditLog::record(
            'update',
            'user',
            "Permisos o estado modificados: {$user->email}",
            $user,
            array_intersect_key($user->getOriginal(), $changed),
            $changed
        ));
    }

    public function deleted(User $user): void
    {
        $this->safely(fn () => AuditLog::record(
            'delete',
            'user',
            "Usuario eliminado: {$user->email}",
            $user,
            $this->visible($user->getAttributes())
        ));
    }

    /** Quita del registro todo lo que no debe quedar escrito. */
    private function visible(array $attributes): array
    {
        return array_diff_key($attributes, array_flip([
            'password', 'remember_token', 'api_token', 'restaurant_pin',
        ]));
    }

    /** La bitacora nunca puede impedir que se guarde un usuario. */
    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            \Log::warning('[UserSecurityObserver] No se pudo auditar el cambio.', ['error' => $e->getMessage()]);
        }
    }
}
