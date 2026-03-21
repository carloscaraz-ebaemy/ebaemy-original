<?php

namespace App\Broadcasting;

use App\Models\Tenant\User;
use Hyn\Tenancy\Environment;

/**
 * Canal privado del almacén filtrado por tenant.
 *
 * Acceso permitido a roles: admin, warehouse.
 *
 * Registro en routes/channels.php:
 *   Broadcast::channel('warehouse.{tenantUuid}', WarehouseChannel::class);
 *
 * Suscripción Vue 3 (Echo + Pusher/Reverb):
 *   Echo.private(`warehouse.${tenantUuid}`)
 *     .listen('ProvinceOrderCreated', handler)
 *     .listen('OrderStatusChanged', handler)
 *     .listen('OrderDispatched', handler);
 */
class WarehouseChannel
{
    public function join(User $user, string $tenantUuid): bool
    {
        // Verifica que el usuario pertenece al tenant del canal
        /** @var Environment $tenancy */
        $tenancy = app(Environment::class);
        $currentTenant = $tenancy->tenant();

        if (!$currentTenant || $currentTenant->uuid !== $tenantUuid) {
            return false;
        }

        return in_array($user->type ?? '', ['admin', 'warehouse', 'superadmin']);
    }
}
