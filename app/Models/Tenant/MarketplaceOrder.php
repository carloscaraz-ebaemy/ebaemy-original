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
        'invoice_uploaded_at', 'invoice_upload_error', 'stock_restored_at',
    ];

    protected $casts = [
        'customer_data' => 'array',
        'items_data' => 'array',
        'shipping_data' => 'array',
        'total' => 'float',
        'ordered_at' => 'datetime',
        'processed_at' => 'datetime',
        'invoice_uploaded_at' => 'datetime',
        'stock_restored_at' => 'datetime',
    ];

    public function channel() { return $this->belongsTo(MarketplaceChannel::class, 'channel_id'); }
    public function order() { return $this->belongsTo(Order::class); }
    public function saleNote() { return $this->belongsTo(SaleNote::class); }
    public function document() { return $this->belongsTo(Document::class); }

    public function scopePending($q) { return $q->where('status', 'pending'); }

    /**
     * ¿Este tenant tiene la tabla de pedidos de marketplace externo?
     *
     * Misma guarda que `ShippingRequest::moduleInstalled()`, y por la misma
     * razon: `Gestion de Pedidos` hace eager loading y `whereHas` sobre esta
     * relacion, de modo que en un tenant donde la tabla no exista —migracion
     * caida, tenant recien creado desde un snapshot viejo— el listado ENTERO
     * se cae con un 1146 en vez de mostrar los pedidos que si tiene.
     *
     * Memorizado por BASE DE DATOS, no por proceso: un worker de cola atiende
     * varios tenants seguidos y una memo global le daria la respuesta del
     * anterior.
     */
    public static function moduleInstalled(): bool
    {
        static $installed = [];

        try {
            $schema   = \Illuminate\Support\Facades\Schema::connection('tenant');
            $database = $schema->getConnection()->getDatabaseName();

            if (!array_key_exists($database, $installed)) {
                $installed[$database] = $schema->hasTable('marketplace_orders');
            }

            return $installed[$database];
        } catch (\Throwable $e) {
            return false;
        }
    }

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

        // Antes esto era un `name LIKE '%falabella%'` que no encontraba nada:
        // ningun canal sembrado se llama asi, y los pedidos de Saga entraban con
        // `channel_id` NULL — fuera de todo filtro y de `channelReport()`.
        $salesChannel = SalesChannel::marketplacePlatformChannel(
            $channel->platform ?? null,
            $channel->name ?? null
        );

        $warehouseId = $salesChannel?->warehouse_id
            ?: optional(\Modules\Inventory\Models\Warehouse::first())->id;

        $order = Order::crearTolerandoEsquemaViejo([
            'external_id'        => (string) \Illuminate\Support\Str::uuid(),
            'customer'           => $this->customer_data ?? [],
            'items'              => $this->normalizedItems(),
            'total'              => $this->total,
            'shipping_address'   => $this->shipping_data['address'] ?? 'Marketplace',
            'status_order_id'    => $this->erpStatusId(),
            // En Saga el cliente ya pagó, pero NO por una pasarela nuestra:
            // Falabella cobra y liquida fuera del sistema. Ese hecho vive en
            // `status_order_id` (2 = pago verificado) y en `paid_at`, no en
            // `payment_status`, que ahora solo describe la pasarela. Ver el
            // bloque «Estado del pago» en App\Models\Tenant\Order.
            'payment_status'     => null,
            'paid_at'            => $this->ordered_at ?: now(),
            'reference_payment'  => 'marketplace_' . ($channel->platform ?? 'unknown'),
            'channel_id'         => $salesChannel?->id,
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
     * Normaliza los items crudos de Saga (items_data: Name/PaidPrice/…) a la
     * estructura que consume el ERP: el detalle de /orders (nombre, cantidad,
     * sale_unit_price, currency_type_id, exchange_rate_sale) y la generación de
     * Nota de Venta (item_id, quantity, sale_unit_price). Sin esto, el detalle
     * sale "S/ NaN" y la NV queda con precios/cantidades en cero.
     */
    public function normalizedItems(): array
    {
        $raw = is_array($this->items_data) ? $this->items_data : [];
        if (isset($raw['OrderItemId'])) {
            $raw = [$raw];
        }

        $out = [];
        foreach ($raw as $it) {
            if (!is_array($it)) {
                continue;
            }
            $name  = $it['Name'] ?? $it['ProductName'] ?? 'Producto';
            $qty   = (float) ($it['Quantity'] ?? 1) ?: 1;
            $price = (float) ($it['PaidPrice'] ?? $it['ItemPrice'] ?? 0);
            $sku   = $it['ShopSku'] ?? $it['Sku'] ?? null;

            $itemId = null;
            if ($sku) {
                $itemId = MarketplaceProduct::where('channel_id', $this->channel_id)
                    ->where('external_sku', $sku)
                    ->value('item_id');
            }

            $out[] = [
                'item_id'            => $itemId,
                'nombre'             => $name,
                'descripcion'        => $name,
                'description'        => $name,
                'cantidad'           => $qty,
                'quantity'           => $qty,
                'sale_unit_price'    => $price,
                'unit_price'         => $price,
                'currency_type_id'   => 'PEN',
                'exchange_rate_sale' => 1,
            ];
        }

        // Fallback: una línea por el total para que el detalle no quede vacío.
        if (empty($out)) {
            $out[] = [
                'item_id'            => null,
                'nombre'             => 'Pedido Saga #' . $this->external_order_id,
                'descripcion'        => 'Pedido Saga #' . $this->external_order_id,
                'description'        => 'Pedido Saga #' . $this->external_order_id,
                'cantidad'           => 1,
                'quantity'           => 1,
                'sale_unit_price'    => (float) $this->total,
                'unit_price'         => (float) $this->total,
                'currency_type_id'   => 'PEN',
                'exchange_rate_sale' => 1,
            ];
        }

        return $out;
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

        // Antes esto rellenaba `payment_status = 'paid'`. Saga no pasa por una
        // pasarela nuestra, asi que lo que corresponde es dejar constancia de
        // CUANDO se cobro, no inventar un estado de pasarela que no existio.
        if (empty($order->paid_at) && $this->ordered_at) {
            $order->paid_at = $this->ordered_at;
        }

        $order->save();
    }
}
