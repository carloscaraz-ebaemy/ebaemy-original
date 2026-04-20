<?php

namespace App\Policies;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Tenant\Order;
use App\Models\Tenant\User;

/**
 * OrderPolicy — Reglas de autorización para el pedido ecommerce.
 *
 * El método principal es `transitionTo()` que encapsula las dos reglas
 * que antes vivían dispersas en `OrderController::updateStatusOrders`:
 *   1. El mapa de transiciones válidas entre status_order_id.
 *   2. El guard de `payment_status` para la transición 1→2 (no verificar
 *      el pago si Culqi lo dejó `pending_capture` o `capture_failed`).
 *
 * Uso desde controller:
 *     $this->authorize('transitionTo', [$order, $toStatus]);
 *
 * Uso programático (sin excepción):
 *     Gate::forUser($user)->allows('transitionTo', [$order, $toStatus]);
 *
 * Transiciones permitidas:
 *   1 → [2, 5]   (Pendiente → Pago verificado / Cancelado)
 *   2 → [3, 5]   (Pago verificado → En preparación / Cancelado)
 *   3 → [4, 5]   (En preparación → Despachado / Cancelado)
 *   4 → [6, 5]   (Despachado → Entregado / Cancelado)
 *   5, 6 → []    (finales, solo lectura)
 */
class OrderPolicy
{
    /**
     * Mapa canónico de transiciones permitidas.
     * Fuente de verdad compartida entre controller, policy y frontend.
     */
    public const ALLOWED_TRANSITIONS = [
        1 => [2, 5],
        2 => [3, 5],
        3 => [4, 5],
        4 => [6, 5],
        5 => [],
        6 => [],
    ];

    /**
     * Estados de pago que bloquean la verificación manual (1→2).
     * Si Culqi aún no capturó (`pending_capture`) o falló (`capture_failed`),
     * el admin no debe poder marcar el pago como verificado.
     */
    public const BLOCKED_PAYMENT_STATUSES_FOR_VERIFY = [
        'pending_capture',
        'capture_failed',
    ];

    /**
     * Admin y superadmin tienen acceso total a todas las acciones
     * (las reglas de negocio se aplican en los métodos específicos, no aquí).
     */
    public function before(?User $user, string $ability): ?bool
    {
        if ($user && in_array($user->type ?? '', ['superadmin'], true)) {
            return true;
        }
        return null;
    }

    /**
     * Autoriza la transición de un pedido al estado destino.
     *
     * @param User  $user
     * @param Order $order
     * @param int   $toStatus Estado destino (1..6)
     * @return bool
     * @throws InvalidOrderTransitionException Si la transición es inválida
     *   (se lanza dentro del boolean-check para que el mensaje suba al controller).
     */
    public function transitionTo(?User $user, Order $order, int $toStatus): bool
    {
        $fromStatus = (int) $order->status_order_id;

        if ($fromStatus === $toStatus) {
            return true;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];
        if (!in_array($toStatus, $allowed, true)) {
            throw new InvalidOrderTransitionException(
                "Transición inválida de estado: {$fromStatus} → {$toStatus}"
            );
        }

        // Guard 1→2: no verificar pago con Culqi en estado no resuelto.
        if ($toStatus === 2 && in_array($order->payment_status, self::BLOCKED_PAYMENT_STATUSES_FOR_VERIFY, true)) {
            throw new InvalidOrderTransitionException(
                "No se puede verificar el pago: estado Culqi = '{$order->payment_status}'. " .
                "Espera que la captura termine o rechaza el pedido."
            );
        }

        // Regla de rol: empleados 'pos' no pueden cancelar pedidos despachados.
        // (ejemplo extensible — hoy cualquier admin puede cancelar)
        if ($toStatus === 5 && $user && ($user->type ?? '') === 'cashier' && $order->dispatched_at) {
            return false;
        }

        return true;
    }

    /**
     * Ver el historial (order_status_logs) de un pedido.
     */
    public function viewHistory(?User $user, Order $order): bool
    {
        return $user !== null;
    }
}
