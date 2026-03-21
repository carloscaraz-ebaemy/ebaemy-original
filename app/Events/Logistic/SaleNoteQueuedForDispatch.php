<?php

namespace App\Events\Logistic;

use App\Models\Tenant\SaleNote;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica al almacenero en tiempo real cuando una Nota de Venta
 * entra a la cola de despacho (PENDIENTE).
 *
 * Canal: private-warehouse.{tenant_uuid}
 *
 * Frontend:
 *   Echo.private(`warehouse.${tenantUuid}`)
 *     .listen('.SaleNoteQueuedForDispatch', (e) => {
 *       showToast(e.saleNote);
 *     });
 */
class SaleNoteQueuedForDispatch implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly SaleNote $saleNote,
        public readonly string $tenantUuid
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("warehouse.{$this->tenantUuid}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'SaleNoteQueuedForDispatch';
    }

    public function broadcastWith(): array
    {
        return [
            'saleNote' => [
                'id'            => $this->saleNote->id,
                'number_full'   => $this->saleNote->number_full,
                'customer_name' => $this->saleNote->customer['name'] ?? '—',
                'total'         => $this->saleNote->total,
                'currency'      => $this->saleNote->currency_type_id,
                'delivery_type' => $this->saleNote->delivery_type?->value,
                'delivery_label'=> $this->saleNote->delivery_type?->label(),
                'is_urgent'     => $this->saleNote->is_urgent,
                'items_count'   => $this->saleNote->items->count(),
                'queue_url'     => route('logistic.sale_notes.queue'),
            ],
        ];
    }
}
