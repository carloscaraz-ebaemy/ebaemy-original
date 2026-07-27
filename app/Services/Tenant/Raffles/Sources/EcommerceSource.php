<?php

namespace App\Services\Tenant\Raffles\Sources;

use App\Models\Tenant\Order;
use App\Models\Tenant\Raffle;
use App\Services\Tenant\Raffles\ParticipantSource;
use Illuminate\Support\Collection;

/**
 * Tienda Virtual: pedidos de la tabla `orders`.
 *
 * `orders` guarda su canasta en la columna JSON `items`, sin tabla puente.
 * El filtro por categoría / producto se resuelve con JSON_CONTAINS sobre
 * `$[*].id` y `$[*].category.id` (verificado en MySQL 8).
 */
class EcommerceSource extends ParticipantSource
{
    public function key(): string
    {
        return 'ecommerce';
    }

    public function label(): string
    {
        return 'Tienda Virtual (Ecommerce)';
    }

    public function description(): string
    {
        return 'Clientes que compraron desde la tienda online.';
    }

    public function icon(): string
    {
        return '🛒';
    }

    public function available(): bool
    {
        return $this->tableExists('orders');
    }

    public function filters(): array
    {
        return [
            [
                'key' => 'paid', 'type' => 'boolean', 'default' => true,
                'label' => 'Solo pedidos pagados',
                'help'  => 'Estado "Pago verificado" o cobro capturado en la pasarela.',
            ],
            ['key' => 'date_from', 'type' => 'date', 'label' => 'Pedidos desde'],
            ['key' => 'date_to',   'type' => 'date', 'label' => 'Pedidos hasta'],
            ['key' => 'channel_id', 'type' => 'select', 'label' => 'Canal de venta', 'options' => 'channels'],
            [
                'key' => 'status_order_id', 'type' => 'select',
                'label'   => 'Estado del pedido',
                'options' => [
                    1 => 'Pago sin verificar',
                    2 => 'Pago verificado',
                    3 => 'Despachado',
                    4 => 'Confirmado por el cliente',
                ],
            ],
            ['key' => 'min_ticket', 'type' => 'number', 'label' => 'Monto mínimo por pedido'],
            ['key' => 'categories', 'type' => 'multiselect', 'label' => 'Categorías compradas', 'options' => 'categories'],
            ['key' => 'items', 'type' => 'items', 'label' => 'Productos comprados'],
        ];
    }

    public function resolve(Raffle $raffle): Collection
    {
        $query = Order::query()->whereNotNull('person_id');

        // `orders` no tiene date_of_issue: la fecha del pedido es created_at.
        $this->applyDates($query, $raffle, 'created_at');

        if ($this->boolFilter($raffle, 'paid', true)) {
            $query->where(function ($q) {
                $q->where('status_order_id', '>=', 2)
                  ->orWhere('payment_status', 'captured');
            });
        }

        if ($channel = $this->filter($raffle, 'channel_id')) {
            $query->where('channel_id', $channel);
        }

        if ($status = $this->filter($raffle, 'status_order_id')) {
            $query->where('status_order_id', $status);
        }

        if ($min = (float) $this->filter($raffle, 'min_ticket', 0)) {
            $query->where('total', '>=', $min);
        }

        $this->applyJsonProductFilter($query, $raffle);

        return $this->groupByPerson($query, 'person_id', 'created_at');
    }

    /** Filtra por producto o categoría dentro de la canasta JSON del pedido. */
    private function applyJsonProductFilter($query, Raffle $raffle): void
    {
        $categories = $this->arrayFilter($raffle, 'categories');
        $items      = $this->arrayFilter($raffle, 'items');

        if (empty($categories) && empty($items)) {
            return;
        }

        $query->where(function ($w) use ($categories, $items) {
            foreach ($items as $itemId) {
                $w->orWhereRaw(
                    "JSON_CONTAINS(JSON_EXTRACT(items, '$[*].id'), ?)",
                    [json_encode((int) $itemId)]
                );
            }
            foreach ($categories as $catId) {
                $w->orWhereRaw(
                    "JSON_CONTAINS(JSON_EXTRACT(items, '$[*].category.id'), ?)",
                    [json_encode((int) $catId)]
                );
            }
        });
    }
}
