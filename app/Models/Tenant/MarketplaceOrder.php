<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceOrder extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'channel_id', 'external_order_id', 'status', 'customer_data',
        'items_data', 'shipping_data', 'total', 'currency',
        'order_id', 'sale_note_id', 'document_id', 'ordered_at', 'processed_at',
        'invoice_uploaded_at', 'invoice_upload_error',
    ];

    protected $casts = [
        'customer_data' => 'array',
        'items_data' => 'array',
        'shipping_data' => 'array',
        'total' => 'float',
        'ordered_at' => 'datetime',
        'processed_at' => 'datetime',
        'invoice_uploaded_at' => 'datetime',
    ];

    public function channel() { return $this->belongsTo(MarketplaceChannel::class, 'channel_id'); }
    public function order() { return $this->belongsTo(Order::class); }
    public function saleNote() { return $this->belongsTo(SaleNote::class); }
    public function document() { return $this->belongsTo(Document::class); }

    public function scopePending($q) { return $q->where('status', 'pending'); }

    /**
     * Crea (o devuelve) el Order interno enlazado a este pedido de marketplace.
     * Idempotente: si ya está enlazado, devuelve el existente.
     *
     * IMPORTANTE: NO modifica el `status` del pedido de marketplace — el ciclo de
     * despacho (pending → ready_to_ship → shipped) es independiente del enlace al
     * Order interno. El stock ya se descuenta en FalabellaService::processOrderStock,
     * y Order no tiene observer de stock, así que esto no descuenta dos veces.
     */
    public function createErpOrder(): ?Order
    {
        if ($this->order_id) {
            return $this->order;
        }

        $channel = $this->channel;
        $salesChannel = SalesChannel::where('type', 'marketplace')
            ->where('name', 'LIKE', '%' . ($channel->platform ?? $channel->name) . '%')
            ->first();

        $warehouseId = $salesChannel->warehouse_id
            ?? optional(\Modules\Inventory\Models\Warehouse::first())->id;

        $order = Order::create([
            'external_id'        => (string) \Illuminate\Support\Str::uuid(),
            'customer'           => $this->customer_data ?? [],
            'items'              => $this->items_data ?? [],
            'total'              => $this->total,
            'shipping_address'   => $this->shipping_data['address'] ?? 'Marketplace',
            'status_order_id'    => $this->erpStatusId(),
            'payment_status'     => 'paid', // En Saga el cliente ya pagó (Falabella cobra y liquida)
            'reference_payment'  => 'marketplace_' . ($channel->platform ?? 'unknown'),
            'channel_id'         => $salesChannel->id ?? null,
            'warehouse_id'       => $warehouseId,
            'external_order_ref' => $this->external_order_id,
            'marketplace_notes'  => 'Pedido externo #' . $this->external_order_id . ' de ' . ($channel->name ?? $channel->platform),
            'purchase'           => [],
        ]);

        // La fecha de emisión del Order debe reflejar la fecha real del pedido en
        // Saga (ordered_at), no el momento de la conversión. La tabla `orders`
        // muestra `created_at` como "Fecha emisión".
        if ($this->ordered_at) {
            $order->created_at = $this->ordered_at;
            $order->save();
        }

        $this->order_id = $order->id;
        $this->save();

        return $order;
    }

    /**
     * Mapea el estado de despacho del marketplace al status_order_id del Order
     * interno (la columna que /orders usa como "Estatus del pedido").
     * En Saga el cliente ya pagó, así que nunca es 1 (Pendiente de pago).
     */
    public function erpStatusId(): int
    {
        return [
            'pending'       => 2, // Pago verificado (pagado, por despachar)
            'ready_to_ship' => 3, // En preparación
            'shipped'       => 4, // Despachado
            'delivered'     => 6, // Entregado
            'canceled'      => 5, // Cancelado
        ][$this->status] ?? 2;
    }

    /**
     * Sincroniza el estado del Order interno con el estado de despacho de Saga,
     * para que /orders (centro de control) refleje la realidad sin intervención.
     */
    public function syncErpOrderStatus(): void
    {
        if (!$this->order_id) {
            return;
        }
        $order = $this->order;
        if (!$order) {
            return;
        }
        $order->status_order_id = $this->erpStatusId();
        if (empty($order->payment_status)) {
            $order->payment_status = 'paid';
        }
        $order->save();
    }
}
