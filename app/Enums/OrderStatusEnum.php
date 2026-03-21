<?php

namespace App\Enums;

enum OrderStatusEnum: string
{
    case PENDING         = 'pending';
    case CONFIRMED       = 'confirmed';
    case IN_PREPARATION  = 'in_preparation';
    case DISPATCHED      = 'dispatched';
    case DELIVERED       = 'delivered';
    case CANCELLED       = 'cancelled';

    /**
     * Transiciones permitidas por estado.
     * Solo el almacenero/admin puede avanzar en el flujo provincia.
     */
    public function allowedTransitions(): array
    {
        return match($this) {
            self::PENDING        => [self::CONFIRMED, self::CANCELLED],
            self::CONFIRMED      => [self::IN_PREPARATION, self::CANCELLED],
            self::IN_PREPARATION => [self::DISPATCHED],
            self::DISPATCHED     => [self::DELIVERED],
            self::DELIVERED      => [],
            self::CANCELLED      => [],
        };
    }

    public function canTransitionTo(self $new): bool
    {
        return in_array($new, $this->allowedTransitions());
    }

    public function label(): string
    {
        return match($this) {
            self::PENDING        => 'Pendiente',
            self::CONFIRMED      => 'Confirmado',
            self::IN_PREPARATION => 'En Preparación',
            self::DISPATCHED     => 'Despachado',
            self::DELIVERED      => 'Entregado',
            self::CANCELLED      => 'Cancelado',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::PENDING        => 'warning',
            self::CONFIRMED      => 'info',
            self::IN_PREPARATION => 'primary',
            self::DISPATCHED     => 'success',
            self::DELIVERED      => 'success',
            self::CANCELLED      => 'danger',
        };
    }

    /**
     * Estados que representan una orden activa en el almacén (visible en la cola).
     */
    public static function warehouseActiveStatuses(): array
    {
        return [self::CONFIRMED, self::IN_PREPARATION];
    }

    /**
     * Estados que ya finalizaron el ciclo (no modificables).
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::DELIVERED, self::CANCELLED]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
