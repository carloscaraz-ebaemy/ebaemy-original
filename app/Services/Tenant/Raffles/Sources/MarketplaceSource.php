<?php

namespace App\Services\Tenant\Raffles\Sources;

use App\Models\Tenant\MarketplaceOrder;
use App\Models\Tenant\Raffle;
use App\Services\Tenant\Raffles\ParticipantSource;
use Illuminate\Support\Collection;

/**
 * Marketplace: pedidos entrantes de canales externos (Saga Falabella,
 * MercadoLibre, marketplace ebaemy…) guardados en `marketplace_orders`.
 *
 * Estos pedidos no crean un `Person` del ERP: los datos del comprador llegan
 * en la columna JSON `customer_data` (name / email / phone / document), así
 * que la fila se arma desde ahí.
 */
class MarketplaceSource extends ParticipantSource
{
    public function key(): string
    {
        return 'marketplace';
    }

    public function label(): string
    {
        return 'Marketplace';
    }

    public function description(): string
    {
        return 'Compradores que llegaron por canales externos (Saga, MercadoLibre, ebaemy…).';
    }

    public function icon(): string
    {
        return '🏬';
    }

    public function available(): bool
    {
        return $this->tableExists('marketplace_orders');
    }

    public function unavailableReason(): string
    {
        return 'Tu tienda no tiene pedidos de marketplace.';
    }

    public function filters(): array
    {
        return [
            ['key' => 'date_from', 'type' => 'date', 'label' => 'Pedidos desde'],
            ['key' => 'date_to',   'type' => 'date', 'label' => 'Pedidos hasta'],
            [
                'key' => 'statuses', 'type' => 'multiselect',
                'label'   => 'Estado del pedido',
                'options' => 'marketplace_statuses',
                'help'    => 'Vacío = todos los estados.',
            ],
            ['key' => 'channel_id', 'type' => 'select', 'label' => 'Canal', 'options' => 'marketplace_channels'],
            ['key' => 'min_ticket', 'type' => 'number', 'label' => 'Monto mínimo por pedido'],
        ];
    }

    public function resolve(Raffle $raffle): Collection
    {
        $query = MarketplaceOrder::query();

        $this->applyDates($query, $raffle, 'ordered_at');

        if ($statuses = $this->arrayFilter($raffle, 'statuses')) {
            $query->whereIn('status', $statuses);
        }

        if ($channel = $this->filter($raffle, 'channel_id')) {
            $query->where('channel_id', $channel);
        }

        if ($min = (float) $this->filter($raffle, 'min_ticket', 0)) {
            $query->where('total', '>=', $min);
        }

        // El comprador vive en un JSON: se agrupa en PHP por su identidad.
        $merged = [];

        foreach ($query->get(['customer_data', 'total', 'ordered_at', 'created_at']) as $order) {
            $c = (array) ($order->customer_data ?? []);

            $document = $c['document'] ?? $c['number'] ?? $c['dni'] ?? null;
            $email    = $c['email'] ?? null;
            $phone    = $c['phone'] ?? $c['telephone'] ?? null;
            $name     = trim((string) ($c['name'] ?? ''))
                     ?: trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));

            $key = strtolower(trim((string) ($document ?: $email ?: $phone)));
            if ($key === '') {
                continue;   // sin forma de identificar ni contactar al comprador
            }

            $date = optional($order->ordered_at ?: $order->created_at)->format('Y-m-d');

            if (!isset($merged[$key])) {
                $merged[$key] = $this->row(null, $name, $document, $email, $phone, 1, (float) $order->total, $date);
                continue;
            }

            $merged[$key]['records']++;
            $merged[$key]['amount'] += (float) $order->total;
            if ($date && (!$merged[$key]['last_at'] || $date > $merged[$key]['last_at'])) {
                $merged[$key]['last_at'] = $date;
            }
        }

        return collect(array_values($merged));
    }
}
