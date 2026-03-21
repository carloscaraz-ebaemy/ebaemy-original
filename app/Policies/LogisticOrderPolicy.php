<?php

namespace App\Policies;

use App\Enums\OrderStatusEnum;
use App\Models\Tenant\LogisticOrder;
use App\Models\Tenant\User;

/**
 * LogisticOrderPolicy — Control de acceso por roles para el sistema logístico.
 *
 * Roles del sistema:
 *   admin     → acceso total
 *   warehouse → puede ver cola, iniciar picking, despachar
 *   cashier   → puede crear órdenes tienda/provincia, no gestiona almacén
 *   customer  → solo ve sus propias órdenes
 */
class LogisticOrderPolicy
{
    /**
     * Administradores tienen acceso total solo si tienen el módulo logístico activo.
     */
    public function before(User $user): ?bool
    {
        if ($this->isAdmin($user) && $user->hasLogisticModule()) {
            return true;
        }
        return null;
    }

    /** Ver cola del almacén */
    public function viewWarehouseQueue(User $user): bool
    {
        return $this->isWarehouseOrAdmin($user) && $user->hasLogisticModule();
    }

    /** Ver detalle de un pedido */
    public function view(User $user, LogisticOrder $order): bool
    {
        if ($this->isWarehouseOrAdmin($user)) {
            return true;
        }
        // El cajero ve sus propias órdenes
        if ($user->type === 'cashier') {
            return $order->user_id === $user->id;
        }
        // El cliente ve sus propias órdenes
        return $order->customer_id === $user->person_id;
    }

    /** Crear orden (tienda o provincia) */
    public function create(User $user): bool
    {
        return in_array($user->type ?? '', ['admin', 'cashier', 'warehouse']);
    }

    /** Iniciar preparación (picking) */
    public function startPreparation(User $user, LogisticOrder $order): bool
    {
        if (!$this->isWarehouseOrAdmin($user)) {
            return false;
        }
        return $order->status === OrderStatusEnum::CONFIRMED
            && $order->isProvince();
    }

    /** Despachar pedido y generar guía de remisión */
    public function dispatch(User $user, LogisticOrder $order): bool
    {
        if (!$this->isWarehouseOrAdmin($user)) {
            return false;
        }
        return $order->status === OrderStatusEnum::IN_PREPARATION;
    }

    /** Cancelar pedido */
    public function cancel(User $user, LogisticOrder $order): bool
    {
        if (!$order->canBeCancelledBy()) {
            return false;
        }
        // Admin y warehouse pueden cancelar cualquier orden activa
        if ($this->isWarehouseOrAdmin($user)) {
            return true;
        }
        // El cajero solo cancela órdenes que él creó y están en PENDING
        if ($user->type === 'cashier') {
            return $order->user_id === $user->id
                && $order->status === OrderStatusEnum::PENDING;
        }
        return false;
    }

    /** Ver movimientos de stock de un pedido */
    public function viewStockMovements(User $user): bool
    {
        return $this->isWarehouseOrAdmin($user);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function isAdmin(User $user): bool
    {
        return in_array($user->type ?? '', ['admin', 'superadmin']);
    }

    private function isWarehouseOrAdmin(User $user): bool
    {
        return in_array($user->type ?? '', ['admin', 'superadmin', 'warehouse'])
            && $user->hasLogisticModule();
    }
}
