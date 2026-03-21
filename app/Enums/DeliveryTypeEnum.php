<?php

namespace App\Enums;

enum DeliveryTypeEnum: string
{
    /**
     * Entrega inmediata en tienda/POS.
     * Flujo: descuenta stock_physical al instante → DELIVERED directo.
     * Genera Boleta/Factura al momento de la venta.
     */
    case STORE = 'store';

    /**
     * Despacho a provincia / cliente remoto.
     * Flujo: incrementa stock_committed → cola almacén → picking → guía remisión → DISPATCHED.
     * La Factura/Boleta se puede emitir al confirmar o al despachar (configurable).
     */
    case PROVINCE = 'province';

    /**
     * Retiro en tienda (click & collect del ecommerce).
     * Similar a STORE pero el cliente espera confirmación previa.
     */
    case PICKUP = 'pickup';

    public function label(): string
    {
        return match($this) {
            self::STORE    => 'Entrega Inmediata',
            self::PROVINCE => 'Envío por Courier',
            self::PICKUP   => 'Recojo en Tienda',
        };
    }

    public function badgeColor(): string
    {
        return match($this) {
            self::STORE    => 'secondary',
            self::PROVINCE => 'primary',
            self::PICKUP   => 'info',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::STORE    => 'fas fa-store',
            self::PROVINCE => 'fas fa-truck',
            self::PICKUP   => 'fas fa-hand-holding-box',
        };
    }

    /**
     * Indica si este tipo de entrega requiere pasar por la cola del almacén.
     */
    public function requiresWarehouseQueue(): bool
    {
        return match($this) {
            self::STORE    => false,
            self::PROVINCE => true,
            self::PICKUP   => true,
        };
    }

    /**
     * Indica si debe incrementarse stock_committed al confirmar.
     */
    public function commitsStock(): bool
    {
        return $this->requiresWarehouseQueue();
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
