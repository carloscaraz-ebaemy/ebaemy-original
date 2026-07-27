<?php

namespace App\Services\Tenant\Raffles\Sources;

/**
 * Gestión de Pedidos: todas las ventas del ERP (comprobantes y notas de
 * venta), sin importar por qué canal entraron.
 */
class OrdersManagementSource extends ErpSalesSource
{
    public function key(): string
    {
        return 'orders_management';
    }

    public function label(): string
    {
        return 'Gestión de Pedidos';
    }

    public function description(): string
    {
        return 'Clientes con comprobantes o notas de venta registrados en el ERP.';
    }

    public function icon(): string
    {
        return '🧾';
    }

    public function filters(): array
    {
        return $this->salesFilters();
    }
}
