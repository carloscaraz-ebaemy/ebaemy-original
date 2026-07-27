<?php

namespace App\Services\Tenant\Raffles\Sources;

use App\Models\Tenant\Raffle;
use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\Raffles\ParticipantSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Registro de Envíos: clientes que registraron un envío en el módulo de
 * despacho (`shipping_requests`).
 *
 * A diferencia de las ventas del ERP, aquí NO hay `person_id`: el cliente se
 * identifica por el documento y el teléfono que dejó en el formulario, así que
 * la agrupación se hace sobre esas columnas.
 */
class ShipmentsSource extends ParticipantSource
{
    public function key(): string
    {
        return 'shipments';
    }

    public function label(): string
    {
        return 'Registro de Envíos';
    }

    public function description(): string
    {
        return 'Clientes que registraron un envío a domicilio o por agencia.';
    }

    public function icon(): string
    {
        return '📦';
    }

    public function available(): bool
    {
        return $this->tableExists('shipping_requests');
    }

    public function unavailableReason(): string
    {
        return 'Tu tienda no tiene el módulo de Registro de Envíos.';
    }

    public function filters(): array
    {
        return [
            [
                'key' => 'paid', 'type' => 'boolean', 'default' => true,
                'label' => 'Solo con pago confirmado',
                'help'  => 'Envíos marcados como pagados por el encargado.',
            ],
            [
                'key' => 'completed', 'type' => 'boolean', 'default' => false,
                'label' => 'Solo envíos completados',
                'help'  => 'Entregados al cliente o dejados en la agencia.',
            ],
            ['key' => 'date_from', 'type' => 'date', 'label' => 'Registrados desde'],
            ['key' => 'date_to',   'type' => 'date', 'label' => 'Registrados hasta'],
            [
                'key' => 'statuses', 'type' => 'multiselect',
                'label'   => 'Estado del envío',
                'options' => ShippingRequest::STATUSES,
                'help'    => 'Vacío = cualquier estado salvo anulado.',
            ],
            [
                'key' => 'delivery_type', 'type' => 'select',
                'label'   => 'Tipo de entrega',
                'options' => ShippingRequest::DELIVERY_TYPES,
            ],
            ['key' => 'agency', 'type' => 'text', 'label' => 'Agencia de transporte', 'help' => 'Coincidencia parcial, p. ej. "Shalom".'],
        ];
    }

    public function resolve(Raffle $raffle): Collection
    {
        $query = DB::connection('tenant')->table('shipping_requests')
                   ->where('status', '!=', ShippingRequest::STATUS_ANULADO);

        $this->applyDates($query, $raffle, 'created_at');

        if ($this->boolFilter($raffle, 'paid', true)) {
            $query->where('payment_confirmed', 1);
        }

        if ($this->boolFilter($raffle, 'completed')) {
            $query->whereIn('status', [ShippingRequest::STATUS_ENTREGADO, ShippingRequest::STATUS_EN_AGENCIA]);
        }

        if ($statuses = $this->arrayFilter($raffle, 'statuses')) {
            $query->whereIn('status', $statuses);
        }

        if ($type = $this->filter($raffle, 'delivery_type')) {
            $query->where('delivery_type', $type);
        }

        if ($agency = trim((string) $this->filter($raffle, 'agency'))) {
            $query->where('shipping_agency', 'like', "%{$agency}%");
        }

        // Sin person_id: se agrupa por documento y, si no hay, por teléfono.
        $rows = $query->selectRaw(
                    "coalesce(nullif(dni, ''), nullif(phone, ''), concat('id:', id)) as k,
                     max(full_name) as full_name, max(dni) as dni, max(phone) as phone,
                     count(*) as records, coalesce(sum(delivery_price), 0) as amount,
                     max(created_at) as last_at"
                )
                ->groupBy('k')
                ->get();

        return $rows->map(fn ($r) => $this->row(
            null,
            $r->full_name,
            $r->dni,
            null,
            $r->phone,
            (int) $r->records,
            (float) $r->amount,
            $r->last_at ? substr((string) $r->last_at, 0, 10) : null
        ))->values();
    }
}
