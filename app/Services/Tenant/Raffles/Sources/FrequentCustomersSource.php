<?php

namespace App\Services\Tenant\Raffles\Sources;

use App\Models\Tenant\Document;
use App\Models\Tenant\Order;
use App\Models\Tenant\Raffle;
use App\Models\Tenant\SaleNote;
use App\Services\Tenant\Raffles\ParticipantSource;
use Illuminate\Support\Collection;

/**
 * Clientes frecuentes: quienes compraron al menos N veces (o gastaron al
 * menos X) en el periodo, sumando comprobantes, notas de venta y pedidos de
 * tienda virtual.
 *
 * Es el único origen cuyo criterio es de VOLUMEN, no de módulo: por eso suma
 * las tres fuentes de venta y recién después aplica el umbral.
 */
class FrequentCustomersSource extends ParticipantSource
{
    protected const VALID_STATE_TYPES = ['01', '03', '05', '07'];

    public function key(): string
    {
        return 'frequent_customers';
    }

    public function label(): string
    {
        return 'Clientes frecuentes';
    }

    public function description(): string
    {
        return 'Quienes compraron varias veces o superaron un monto acumulado.';
    }

    public function icon(): string
    {
        return '⭐';
    }

    public function filters(): array
    {
        return [
            [
                'key' => 'min_purchases', 'type' => 'number', 'default' => 3,
                'label' => 'Compras mínimas en el periodo',
                'help'  => 'Suma comprobantes, notas de venta y pedidos de tienda virtual.',
            ],
            ['key' => 'min_total', 'type' => 'number', 'label' => 'Monto acumulado mínimo'],
            ['key' => 'date_from', 'type' => 'date', 'label' => 'Compras desde'],
            ['key' => 'date_to',   'type' => 'date', 'label' => 'Compras hasta'],
            [
                'key' => 'paid', 'type' => 'boolean', 'default' => true,
                'label' => 'Contar solo compras pagadas',
            ],
        ];
    }

    public function resolve(Raffle $raffle): Collection
    {
        $rows = $this->erpRows($raffle, Document::query(), 'documents', 'document_payments', 'document_id')
            ->concat($this->erpRows($raffle, SaleNote::query(), 'sale_notes', 'sale_note_payments', 'sale_note_id'))
            ->concat($this->orderRows($raffle));

        // Consolidar por cliente ANTES de aplicar el umbral: 2 boletas + 1
        // pedido online son 3 compras del mismo cliente.
        $merged = [];

        foreach ($rows as $row) {
            $id = $row['person_id'];

            if (!isset($merged[$id])) {
                $merged[$id] = $row;
                continue;
            }

            $merged[$id]['records'] += $row['records'];
            $merged[$id]['amount']  += $row['amount'];

            if ($row['last_at'] && (!$merged[$id]['last_at'] || $row['last_at'] > $merged[$id]['last_at'])) {
                $merged[$id]['last_at'] = $row['last_at'];
            }
        }

        $minPurchases = max(1, (int) $this->filter($raffle, 'min_purchases', 3));
        $minTotal     = (float) $this->filter($raffle, 'min_total', 0);

        return collect(array_values($merged))
            ->filter(fn ($r) => $r['records'] >= $minPurchases && $r['amount'] >= $minTotal)
            ->values();
    }

    private function erpRows(Raffle $raffle, $query, string $table, string $paymentsTable, string $paymentsFk): Collection
    {
        $query->whereIn('state_type_id', self::VALID_STATE_TYPES)->whereNotNull('customer_id');

        $this->applyDates($query, $raffle, 'date_of_issue');

        if ($this->boolFilter($raffle, 'paid', true)) {
            $query->where(function ($q) use ($table, $paymentsTable, $paymentsFk) {
                $q->where('total_canceled', 1)
                  ->orWhereRaw("(select coalesce(sum(payment), 0) from {$paymentsTable}
                                 where {$paymentsTable}.{$paymentsFk} = {$table}.id) >= {$table}.total");
            });
        }

        return $this->groupByPerson($query, 'customer_id', 'date_of_issue');
    }

    private function orderRows(Raffle $raffle): Collection
    {
        if (!$this->tableExists('orders')) {
            return collect();
        }

        $query = Order::query()->whereNotNull('person_id');

        $this->applyDates($query, $raffle, 'created_at');

        if ($this->boolFilter($raffle, 'paid', true)) {
            $query->where(function ($q) {
                $q->where('status_order_id', '>=', 2)->orWhere('payment_status', 'captured');
            });
        }

        return $this->groupByPerson($query, 'person_id', 'created_at');
    }
}
