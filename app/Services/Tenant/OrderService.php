<?php

namespace App\Services\Tenant;

use App\Enums\DeliveryTypeEnum;
use App\Enums\OrderStatusEnum;
use App\Enums\StockMovementTypeEnum;
use App\Events\Logistic\OrderDispatched;
use App\Events\Logistic\OrderStatusChanged;
use App\Events\Logistic\ProvinceOrderCreated;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Tenant\Document;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\LogisticOrder;
use App\Models\Tenant\LogisticOrderItem;
use App\Models\Tenant\LogisticShippingGuide;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Hyn\Tenancy\Environment;

/**
 * OrderService — Núcleo de la lógica de pedidos logísticos.
 *
 * Diferencia dos flujos:
 *   STORE    → Entrega inmediata. Descuenta stock_physical al instante.
 *              Genera comprobante (Boleta/Factura) y finaliza.
 *   PROVINCE → Cola almacén. Reserva stock_committed.
 *              El almacenero hace picking → despacha con guía de remisión.
 *
 * TODAS las operaciones de stock usan:
 *   - DB::transaction() para atomicidad
 *   - lockForUpdate() para concurrencia
 */
class OrderService
{
    public function __construct(
        private readonly BillingService $billingService
    ) {}

    // ═══════════════════════════════════════════════════════════════
    // CREAR ORDEN — TIENDA (entrega inmediata)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Crea una orden de tienda: valida stock, descuenta físico,
     * genera comprobante (Boleta/Factura) y marca como DELIVERED.
     *
     * @param array $data {
     *   customer_id, warehouse_id, items: [{item_id, quantity, unit_price, affectation_igv_type_id}],
     *   currency_type_id, document_type_id (01=Factura|03=Boleta), series, notes, source
     * }
     */
    public function createStoreOrder(array $data): LogisticOrder
    {
        return DB::transaction(function () use ($data) {

            // 1. Validar stock disponible con lock pesimista
            $this->validateAndLockStock($data['items'], $data['warehouse_id']);

            // 2. Crear la orden logística
            $order = $this->buildOrder($data, DeliveryTypeEnum::STORE);
            $order->status       = OrderStatusEnum::CONFIRMED;
            $order->confirmed_at = now();
            $order->save();

            // 3. Crear ítems de la orden
            $items = $this->createOrderItems($order, $data['items']);

            // 4. Calcular totales
            $this->recalculateTotals($order);

            // 5. Descontar stock_physical de cada ítem
            foreach ($items as $orderItem) {
                $iw = ItemWarehouse::where('item_id', $orderItem->item_id)
                                   ->where('warehouse_id', $orderItem->warehouse_id)
                                   ->lockForUpdate()
                                   ->firstOrFail();

                $iw->applyStockMovement(StockMovementTypeEnum::SALE_STORE, $orderItem->quantity);

                StockMovement::record(
                    $iw,
                    StockMovementTypeEnum::SALE_STORE,
                    $orderItem->quantity,
                    auth()->id(),
                    $order
                );
            }

            // 6. Generar comprobante facturable (Boleta/Factura SUNAT)
            $document = $this->billingService->generateDocument($order, $data);
            $order->document_id = $document->id;

            // 7. Marcar como entregado
            $order->status       = OrderStatusEnum::DELIVERED;
            $order->delivered_at = now();
            $order->save();

            Log::info('[OrderService] Venta tienda completada', [
                'order_id'    => $order->id,
                'document_id' => $document->id,
                'total'       => $order->total,
            ]);

            return $order->load('items', 'document');
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // CREAR ORDEN — PROVINCIA (cola almacén)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Crea una orden provincia: valida stock, reserva stock_committed,
     * ingresa a la cola del almacén y notifica al almacenero en tiempo real.
     *
     * @param array $data {
     *   customer_id, warehouse_id, items, destination_district,
     *   destination_address, recipient_name, recipient_phone,
     *   currency_type_id, notes, source,
     *   document_type_id (opcional — puede emitirse al despachar)
     * }
     */
    public function createProvinceOrder(array $data): LogisticOrder
    {
        return DB::transaction(function () use ($data) {

            // 1. Validar y bloquear stock_available
            $this->validateAndLockStock($data['items'], $data['warehouse_id']);

            // 2. Crear orden
            $order = $this->buildOrder($data, DeliveryTypeEnum::PROVINCE);
            $order->status       = OrderStatusEnum::CONFIRMED;
            $order->confirmed_at = now();
            $order->save();

            // 3. Crear ítems
            $items = $this->createOrderItems($order, $data['items']);
            $this->recalculateTotals($order);

            // 4. Incrementar stock_committed (reserva para almacén)
            foreach ($items as $orderItem) {
                $iw = ItemWarehouse::where('item_id', $orderItem->item_id)
                                   ->where('warehouse_id', $orderItem->warehouse_id)
                                   ->lockForUpdate()
                                   ->firstOrFail();

                $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_COMMIT, $orderItem->quantity);

                StockMovement::record(
                    $iw,
                    StockMovementTypeEnum::PROVINCE_COMMIT,
                    $orderItem->quantity,
                    auth()->id(),
                    $order,
                    "Reserva pedido provincia #{$order->id}"
                );
            }

            // 5. Si se indicó emitir comprobante al confirmar
            if (!empty($data['emit_document_on_confirm']) && !empty($data['document_type_id'])) {
                $document = $this->billingService->generateDocument($order, $data);
                $order->document_id = $document->id;
                $order->save();
            }

            // 6. Notificar al almacenero en tiempo real (Broadcasting)
            $tenantUuid = $this->getTenantUuid();
            event(new ProvinceOrderCreated($order->load('items'), $tenantUuid));

            Log::info('[OrderService] Pedido provincia creado', [
                'order_id'     => $order->id,
                'destination'  => $order->destination_district,
                'total'        => $order->total,
            ]);

            return $order->load('items', 'customer');
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // PICKING — El almacenero toma el pedido
    // ═══════════════════════════════════════════════════════════════

    /**
     * El almacenero inicia la preparación del pedido (picking).
     * Solo pedidos en estado CONFIRMED pueden iniciar preparación.
     */
    public function processPickup(LogisticOrder $order, int $warehouseUserId): LogisticOrder
    {
        $this->assertTransition($order, OrderStatusEnum::IN_PREPARATION);

        return DB::transaction(function () use ($order, $warehouseUserId) {
            $previousStatus = $order->status;

            $order->status            = OrderStatusEnum::IN_PREPARATION;
            $order->warehouse_user_id = $warehouseUserId;
            $order->preparation_at    = now();
            $order->save();

            $tenantUuid = $this->getTenantUuid();
            event(new OrderStatusChanged($order, $previousStatus, $tenantUuid));

            Log::info('[OrderService] Picking iniciado', [
                'order_id'          => $order->id,
                'warehouse_user_id' => $warehouseUserId,
            ]);

            return $order;
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // DESPACHO — Genera guía de remisión y descuenta stock definitivo
    // ═══════════════════════════════════════════════════════════════

    /**
     * Despacha el pedido provincia:
     *   1. Genera la guía de remisión (PDF + registro)
     *   2. Descuenta stock_committed y stock_physical
     *   3. Emite comprobante SUNAT si no fue emitido antes
     *   4. Notifica en tiempo real
     *
     * @param array $guideData {
     *   carrier_name, carrier_ruc, carrier_plate, driver_name, driver_license,
     *   origin_address, destination_address, destination_ubigeo, dispatch_date,
     *   series (opcional), document_type_id (si no emitió antes)
     * }
     */
    public function dispatchOrder(LogisticOrder $order, array $guideData): LogisticOrder
    {
        $this->assertTransition($order, OrderStatusEnum::DISPATCHED);

        return DB::transaction(function () use ($order, $guideData) {
            $previousStatus = $order->status;

            // 1. Generar guía de remisión
            $guide = $this->generateShippingGuide($order, $guideData);

            // 2. Descontar stock_committed + stock_physical por cada ítem
            foreach ($order->items as $orderItem) {
                $iw = ItemWarehouse::where('item_id', $orderItem->item_id)
                                   ->where('warehouse_id', $orderItem->warehouse_id)
                                   ->lockForUpdate()
                                   ->firstOrFail();

                $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_DISPATCH, $orderItem->quantity);

                StockMovement::record(
                    $iw,
                    StockMovementTypeEnum::PROVINCE_DISPATCH,
                    $orderItem->quantity,
                    auth()->id(),
                    $order,
                    "Despacho orden #{$order->id} — Guía {$guide->full_number}"
                );
            }

            // 3. Emitir comprobante SUNAT si no fue emitido al confirmar
            if (!$order->document_id && !empty($guideData['document_type_id'])) {
                $document = $this->billingService->generateDocument($order, $guideData);
                $order->document_id = $document->id;
            }

            // 4. Actualizar estado
            $order->shipping_guide_id = $guide->id;
            $order->status            = OrderStatusEnum::DISPATCHED;
            $order->dispatched_at     = now();
            $order->save();

            // 5. Broadcasting
            $tenantUuid = $this->getTenantUuid();
            event(new OrderDispatched($order, $guide, $tenantUuid));
            event(new OrderStatusChanged($order, $previousStatus, $tenantUuid));

            Log::info('[OrderService] Pedido despachado', [
                'order_id'  => $order->id,
                'guide'     => $guide->full_number,
                'tracking'  => $guide->tracking_code,
            ]);

            return $order->load('items', 'shippingGuide', 'document');
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // CANCELAR ORDEN
    // ═══════════════════════════════════════════════════════════════

    /**
     * Cancela una orden. Si era provincia, libera el stock_committed.
     */
    public function cancelOrder(LogisticOrder $order, string $reason = ''): LogisticOrder
    {
        $this->assertTransition($order, OrderStatusEnum::CANCELLED);

        return DB::transaction(function () use ($order, $reason) {
            $previousStatus = $order->status;

            // Liberar stock_committed si era una orden provincia con stock reservado
            if ($order->isProvince() && in_array($order->status, [
                OrderStatusEnum::CONFIRMED,
                OrderStatusEnum::IN_PREPARATION,
            ])) {
                foreach ($order->items as $orderItem) {
                    $iw = ItemWarehouse::where('item_id', $orderItem->item_id)
                                       ->where('warehouse_id', $orderItem->warehouse_id)
                                       ->lockForUpdate()
                                       ->firstOrFail();

                    $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_CANCEL, $orderItem->quantity);

                    StockMovement::record(
                        $iw,
                        StockMovementTypeEnum::PROVINCE_CANCEL,
                        $orderItem->quantity,
                        auth()->id(),
                        $order,
                        "Cancelación orden #{$order->id}: {$reason}"
                    );
                }
            }

            $order->status       = OrderStatusEnum::CANCELLED;
            $order->cancel_reason = $reason;
            $order->cancelled_at = now();
            $order->save();

            $tenantUuid = $this->getTenantUuid();
            event(new OrderStatusChanged($order, $previousStatus, $tenantUuid));

            return $order;
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // MÉTODOS PRIVADOS / HELPERS
    // ═══════════════════════════════════════════════════════════════

    /**
     * Valida que hay stock_available suficiente para cada ítem del pedido.
     * Usa lockForUpdate() para evitar race conditions.
     *
     * @throws InsufficientStockException
     */
    private function validateAndLockStock(array $items, int $warehouseId): void
    {
        foreach ($items as $item) {
            $iw = ItemWarehouse::where('item_id', $item['item_id'])
                               ->where('warehouse_id', $warehouseId)
                               ->lockForUpdate()
                               ->first();

            if (!$iw || !$iw->hasAvailableStock((float)$item['quantity'])) {
                $itemModel = Item::find($item['item_id']);
                $available = $iw ? $iw->stock_available : 0;

                throw new InsufficientStockException(
                    "Stock insuficiente para '{$itemModel?->description}'. " .
                    "Disponible: {$available}, solicitado: {$item['quantity']}"
                );
            }
        }
    }

    private function buildOrder(array $data, DeliveryTypeEnum $deliveryType): LogisticOrder
    {
        return new LogisticOrder([
            'customer_id'          => $data['customer_id'] ?? null,
            'user_id'              => auth()->id(),
            'warehouse_id'         => $data['warehouse_id'],
            'delivery_type'        => $deliveryType,
            'status'               => OrderStatusEnum::PENDING,
            'destination_district' => $data['destination_district'] ?? null,
            'destination_address'  => $data['destination_address'] ?? null,
            'recipient_name'       => $data['recipient_name'] ?? null,
            'recipient_phone'      => $data['recipient_phone'] ?? null,
            'currency_type_id'     => $data['currency_type_id'] ?? 'PEN',
            'source'               => $data['source'] ?? 'pos',
            'notes'                => $data['notes'] ?? null,
        ]);
    }

    /**
     * Crea los ítems de la orden calculando totales con IGV.
     *
     * @return LogisticOrderItem[]
     */
    private function createOrderItems(LogisticOrder $order, array $items): array
    {
        $created = [];
        foreach ($items as $itemData) {
            $orderItem = new LogisticOrderItem([
                'logistic_order_id'      => $order->id,
                'item_id'                => $itemData['item_id'],
                'warehouse_id'           => $order->warehouse_id,
                'description'            => $itemData['description'] ?? Item::find($itemData['item_id'])?->description ?? '',
                'unit_type_id'           => $itemData['unit_type_id'] ?? 'NIU',
                'quantity'               => $itemData['quantity'],
                'unit_price'             => $itemData['unit_price'],
                'affectation_igv_type_id'=> $itemData['affectation_igv_type_id'] ?? '10',
            ]);

            $orderItem->calculateTotals();
            $orderItem->save();
            $created[] = $orderItem;
        }
        return $created;
    }

    private function recalculateTotals(LogisticOrder $order): void
    {
        $order->refresh();
        $order->subtotal = $order->items->sum('total_base_igv');
        $order->igv      = $order->items->sum('total_igv');
        $order->total    = $order->items->sum('total');
        $order->save();
    }

    private function generateShippingGuide(LogisticOrder $order, array $data): LogisticShippingGuide
    {
        $guide = LogisticShippingGuide::create([
            'logistic_order_id'   => $order->id,
            'carrier_name'        => $data['carrier_name'] ?? null,
            'carrier_ruc'         => $data['carrier_ruc'] ?? null,
            'carrier_plate'       => $data['carrier_plate'] ?? null,
            'driver_name'         => $data['driver_name'] ?? null,
            'driver_license'      => $data['driver_license'] ?? null,
            'origin_address'      => $data['origin_address'] ?? null,
            'destination_address' => $data['destination_address'] ?? $order->destination_address,
            'destination_ubigeo'  => $data['destination_ubigeo'] ?? null,
            'dispatch_date'       => $data['dispatch_date'] ?? now()->toDateString(),
            'tracking_code'       => $data['tracking_code'] ?? null,
            'status'              => 'generated',
            'issued_by'           => auth()->id(),
        ]);

        // Generar PDF de la guía (usa el sistema PDF existente)
        $pdfPath = $this->billingService->generateShippingGuidePdf($order, $guide);
        if ($pdfPath) {
            $guide->pdf_path = $pdfPath;
            $guide->save();
        }

        return $guide;
    }

    /**
     * Valida que la transición de estado sea válida según la máquina de estados.
     *
     * @throws InvalidOrderTransitionException
     */
    private function assertTransition(LogisticOrder $order, OrderStatusEnum $newStatus): void
    {
        if (!$order->status->canTransitionTo($newStatus)) {
            throw new InvalidOrderTransitionException(
                "No se puede pasar de '{$order->status->label()}' a '{$newStatus->label()}'"
            );
        }
    }

    private function getTenantUuid(): string
    {
        /** @var Environment $tenancy */
        $tenancy = app(Environment::class);
        return $tenancy->tenant()?->uuid ?? 'default';
    }
}
