<?php

namespace App\Broadcasting;

use App\Models\Tenant\User;

/**
 * Canal privado para notificar al cliente sobre el estado de su pedido.
 *
 * Registro en routes/channels.php:
 *   Broadcast::channel('customer.{customerId}', CustomerChannel::class);
 */
class CustomerChannel
{
    public function join(User $user, int $customerId): bool
    {
        // El usuario solo puede escuchar su propio canal
        return (int)($user->person_id ?? $user->id) === $customerId;
    }
}
