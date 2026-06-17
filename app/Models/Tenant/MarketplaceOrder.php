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
        'order_id', 'sale_note_id', 'ordered_at', 'processed_at',
    ];

    protected $casts = [
        'customer_data' => 'array',
        'items_data' => 'array',
        'shipping_data' => 'array',
        'total' => 'float',
        'ordered_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function channel() { return $this->belongsTo(MarketplaceChannel::class, 'channel_id'); }
    public function order() { return $this->belongsTo(Order::class); }
    public function saleNote() { return $this->belongsTo(SaleNote::class); }

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
            'status_order_id'    => 1,
            'reference_payment'  => 'marketplace_' . ($channel->platform ?? 'unknown'),
            'channel_id'         => $salesChannel->id ?? null,
            'warehouse_id'       => $warehouseId,
            'external_order_ref' => $this->external_order_id,
            'marketplace_notes'  => 'Pedido externo #' . $this->external_order_id . ' de ' . ($channel->name ?? $channel->platform),
            'purchase'           => [],
        ]);

        $this->order_id = $order->id;
        $this->save();

        return $order;
    }
}
