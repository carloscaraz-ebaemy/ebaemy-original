<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\MarketplaceOrder;
use App\Models\Tenant\MarketplaceSyncLog;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Integración con Falabella Seller Center API
 *
 * Endpoints base: https://sellercenter-api.falabella.com
 * Auth: API Key + User ID (firma HMAC)
 * Docs: https://sellercenter.falabella.com/docs/
 */
class FalabellaService
{
    protected MarketplaceChannel $channel;
    protected string $baseUrl;
    protected string $apiKey;
    protected string $userId;

    public function __construct(MarketplaceChannel $channel)
    {
        $this->channel = $channel;
        $this->baseUrl = $channel->getCredential('api_url', 'https://sellercenter-api.falabella.com');
        $this->apiKey = $channel->getCredential('api_key', '');
        $this->userId = $channel->getCredential('user_id', '');
    }

    // ══════════════════════════════════════════════════════════════
    // AUTHENTICATION — Firma HMAC-SHA256
    // ══════════════════════════════════════════════════════════════

    protected function signRequest(string $action, array $params = []): array
    {
        $params = array_merge($params, [
            'Action' => $action,
            'Format' => 'JSON',
            'Timestamp' => now()->toIso8601String(),
            'UserID' => $this->userId,
            'Version' => '1.0',
        ]);

        ksort($params);
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $params['Signature'] = rawurlencode(hash_hmac('sha256', $queryString, $this->apiKey));

        return $params;
    }

    protected function call(string $action, array $params = [])
    {
        $signed = $this->signRequest($action, $params);

        $response = Http::timeout(30)
            ->retry(3, 1000)
            ->get($this->baseUrl, $signed);

        return $this->handleResponse($action, $response);
    }

    /**
     * Variante POST para acciones de escritura de estado (SetStatusTo*).
     * Seller Center firma TODOS los params en el query string; el body va vacío.
     */
    protected function callPost(string $action, array $params = [])
    {
        $signed = $this->signRequest($action, $params);
        $url = $this->baseUrl . '?' . http_build_query($signed, '', '&', PHP_QUERY_RFC3986);

        $response = Http::timeout(30)->post($url);

        return $this->handleResponse($action, $response);
    }

    protected function handleResponse(string $action, $response)
    {
        if ($response->failed()) {
            $error = $response->json('ErrorResponse.Head.ErrorMessage', $response->body());
            Log::channel('payments')->error("Falabella API error: {$action}", [
                'channel_id' => $this->channel->id,
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new \RuntimeException("Falabella API error [{$action}]: {$error}");
        }

        return $response->json('SuccessResponse.Body') ?? $response->json();
    }

    // ══════════════════════════════════════════════════════════════
    // CONNECTION TEST — Verificar credenciales realmente
    // ══════════════════════════════════════════════════════════════

    /**
     * Construir un servicio a partir de credenciales sueltas (sin canal persistido).
     * Útil para probar la conexión antes de guardar.
     */
    public static function fromCredentials(array $credentials): self
    {
        $channel = new MarketplaceChannel([
            'platform'    => 'falabella',
            'credentials' => $credentials,
        ]);

        return new self($channel);
    }

    /**
     * Probar la conexión haciendo una llamada firmada real a Falabella.
     * A diferencia del check anterior (status < 500), esto valida la firma:
     * sólo retorna success=true si la API responde SuccessResponse.
     */
    public function testConnection(): array
    {
        if ($this->apiKey === '' || $this->userId === '') {
            return ['success' => false, 'message' => 'Falta User ID o API Key'];
        }

        try {
            $signed = $this->signRequest('GetProducts', ['Limit' => 1]);

            $response = Http::timeout(15)->get($this->baseUrl, $signed);
            $json = $response->json() ?? [];

            if (isset($json['SuccessResponse'])) {
                return ['success' => true, 'message' => 'Conexión exitosa con Seller Center'];
            }

            $message = data_get($json, 'ErrorResponse.Head.ErrorMessage')
                ?: data_get($json, 'ErrorResponse.Head.ErrorCode')
                ?: 'Credenciales inválidas (firma rechazada por Falabella)';

            return ['success' => false, 'message' => $message];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'No se pudo contactar a Falabella: ' . $e->getMessage()];
        }
    }

    // ══════════════════════════════════════════════════════════════
    // CATALOG — Leer el catálogo del seller (Saga → EBAEMY)
    // ══════════════════════════════════════════════════════════════

    /**
     * Trae los productos del seller desde Saga (GetProducts).
     * Normaliza la respuesta a una lista plana de productos.
     */
    public function getProducts(array $params = []): array
    {
        $result = $this->call('GetProducts', $params);
        $prods = data_get($result, 'Products.Product', []);

        // La API devuelve un objeto suelto si hay un solo producto.
        if (isset($prods['SellerSku'])) {
            $prods = [$prods];
        }

        return is_array($prods) ? $prods : [];
    }

    // ══════════════════════════════════════════════════════════════
    // PRODUCTS — Crear / Actualizar productos
    // ══════════════════════════════════════════════════════════════

    /**
     * Sincronizar productos del ERP → Falabella
     */
    public function syncProducts(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'sync_products', 'push', function ($log) {
            $mappings = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('sync_status', 'pending')
                ->with(['item', 'variant'])
                ->limit(50) // Batch de 50 (límite API)
                ->get();

            $success = 0;
            $failed = 0;
            $errors = [];

            foreach ($mappings as $mapping) {
                try {
                    $this->pushProduct($mapping);
                    $mapping->update(['sync_status' => 'synced', 'synced_at' => now(), 'last_error' => null]);
                    $success++;
                } catch (\Throwable $e) {
                    $mapping->update(['sync_status' => 'error', 'last_error' => $e->getMessage()]);
                    $errors[] = ['sku' => $mapping->external_sku, 'error' => $e->getMessage()];
                    $failed++;
                }
            }

            return [
                'processed' => $mappings->count(),
                'success' => $success,
                'failed' => $failed,
                'details' => $errors ?: null,
            ];
        })->toArray();
    }

    protected function pushProduct(MarketplaceProduct $mapping): void
    {
        $item = $mapping->item;
        if (!$item) throw new \RuntimeException("Item not found: {$mapping->item_id}");

        $variant = $mapping->variant;
        $sku = $mapping->external_sku ?: $item->internal_id;
        $price = $variant ? ($variant->sale_unit_price ?: $item->sale_unit_price) : $item->sale_unit_price;
        $stock = $variant ? $variant->stock : $item->stock;

        // Falabella ProductCreate XML format
        $xml = $this->buildProductXml([
            'SellerSku' => $sku,
            'Name' => substr($item->description, 0, 255),
            'Description' => $item->name ?: $item->description,
            'Brand' => $item->brand->name ?? 'Genérica',
            'Price' => number_format($price, 2, '.', ''),
            'Quantity' => max(0, (int) $stock),
            'PrimaryCategory' => $item->category->name ?? 'General',
            'ProductId' => $item->barcode ?: $sku,
            'ProductData' => [
                'ShortDescription' => substr($item->description, 0, 500),
            ],
        ]);

        if ($mapping->external_id) {
            // Update existing product
            $this->call('ProductUpdate', ['ProductData' => $xml]);
        } else {
            // Create new product
            $result = $this->call('ProductCreate', ['ProductData' => $xml]);
            $mapping->update(['external_id' => $result['ProductId'] ?? $sku]);
        }
    }

    protected function buildProductXml(array $data): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Request><Product>';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $xml .= "<{$key}>";
                foreach ($value as $k => $v) {
                    $xml .= "<{$k}>" . htmlspecialchars($v) . "</{$k}>";
                }
                $xml .= "</{$key}>";
            } else {
                $xml .= "<{$key}>" . htmlspecialchars($value) . "</{$key}>";
            }
        }
        $xml .= '</Product></Request>';
        return $xml;
    }

    // ══════════════════════════════════════════════════════════════
    // STOCK — Actualizar stock en batch
    // ══════════════════════════════════════════════════════════════

    /**
     * Sincronizar stock de todos los productos mapeados
     */
    public function syncStock(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'sync_stock', 'push', function ($log) {
            // Sincroniza por SellerSku: aplica tanto a productos creados desde EBAEMY
            // como a los que el seller ya tenía en Seller Center (mapeados, status 'pending').
            $mappings = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('sync_status', '!=', 'excluded')
                ->whereNotNull('external_sku')
                ->where('external_sku', '!=', '')
                ->with(['item.warehouses', 'variant'])
                ->get();

            $skus = [];
            foreach ($mappings as $mapping) {
                $stock = $mapping->variant
                    ? max(0, (int) $mapping->variant->stock)
                    : max(0, (int) ($mapping->item->stock ?? 0));

                $skus[] = [
                    'SellerSku' => $mapping->external_sku,
                    'Quantity' => $stock,
                ];
            }

            // Batch update (Falabella acepta hasta 100 por request)
            $chunks = array_chunk($skus, 100);
            $success = 0;
            $failed = 0;

            foreach ($chunks as $chunk) {
                try {
                    $xml = '<?xml version="1.0" encoding="UTF-8"?><Request>';
                    foreach ($chunk as $sku) {
                        $xml .= "<Product><SellerSku>{$sku['SellerSku']}</SellerSku>"
                              . "<Quantity>{$sku['Quantity']}</Quantity></Product>";
                    }
                    $xml .= '</Request>';

                    $this->call('ProductStockUpdate', ['ProductData' => $xml]);
                    $success += count($chunk);
                } catch (\Throwable $e) {
                    $failed += count($chunk);
                }
            }

            return ['processed' => count($skus), 'success' => $success, 'failed' => $failed];
        })->toArray();
    }

    // ══════════════════════════════════════════════════════════════
    // ORDERS — Obtener y procesar pedidos
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtener órdenes pendientes de Falabella
     */
    public function fetchOrders(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'fetch_orders', 'pull', function ($log) {
            $result = $this->call('GetOrders', [
                'Status' => 'pending',
                'SortBy' => 'created_at',
                'SortDirection' => 'DESC',
                'Limit' => 100,
            ]);

            $orders = $result['Orders']['Order'] ?? [];
            if (!is_array($orders)) $orders = [$orders];

            $created = 0;
            $errors = [];

            foreach ($orders as $orderData) {
                try {
                    $externalId = $orderData['OrderId'] ?? null;
                    if (!$externalId) continue;

                    // Evitar duplicados
                    $exists = MarketplaceOrder::where('channel_id', $this->channel->id)
                        ->where('external_order_id', $externalId)
                        ->exists();
                    if ($exists) continue;

                    // Obtener items de la orden
                    $itemsResult = $this->call('GetOrderItems', ['OrderId' => $externalId]);
                    $orderItems = $itemsResult['OrderItems']['OrderItem'] ?? [];

                    // Normalizar: GetOrderItems devuelve objeto suelto si hay 1 item
                    if (isset($orderItems['OrderItemId'])) {
                        $orderItems = [$orderItems];
                    }

                    $mpOrder = MarketplaceOrder::create([
                        'channel_id' => $this->channel->id,
                        'external_order_id' => $externalId,
                        'status' => 'pending',
                        'customer_data' => [
                            'name' => $orderData['CustomerFirstName'] . ' ' . ($orderData['CustomerLastName'] ?? ''),
                            'email' => $orderData['CustomerEmail'] ?? null,
                            'phone' => $orderData['CustomerPhone'] ?? null,
                        ],
                        'items_data' => $orderItems,
                        'shipping_data' => [
                            'address' => $orderData['ShippingAddress'] ?? null,
                            'city' => $orderData['ShippingCity'] ?? null,
                            'method' => $orderData['ShippingType'] ?? null,
                            // Límite de despacho de Saga ("Promised shipping time")
                            'promised_shipping_time' => $orderData['PromisedShippingTime'] ?? null,
                        ],
                        'total' => (float) ($orderData['Price'] ?? 0),
                        'ordered_at' => $orderData['CreatedAt'] ?? now(),
                    ]);

                    // Descontar stock en ERP
                    $this->processOrderStock($orderItems);

                    // Avisar al vendedor (email) que entró un pedido nuevo
                    $this->notifyNewOrder($mpOrder);

                    // Convertir automáticamente a Order interno para que aparezca
                    // en la página Pedidos (no toca el status de despacho). Defensivo:
                    // no rompe el fetch si la conversión falla.
                    try {
                        $mpOrder->createErpOrder();
                    } catch (\Throwable $e) {
                        Log::channel('payments')->warning("No se pudo auto-convertir pedido Saga #{$externalId} a Order: {$e->getMessage()}");
                    }

                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = ['order' => $externalId ?? 'unknown', 'error' => $e->getMessage()];
                }
            }

            return ['processed' => count($orders), 'success' => $created, 'failed' => count($errors), 'details' => $errors ?: null];
        })->toArray();
    }

    /**
     * Descontar stock cuando se recibe una orden de Falabella
     */
    protected function processOrderStock(array $items): void
    {
        foreach ($items as $orderItem) {
            $sku = $orderItem['ShopSku'] ?? $orderItem['Sku'] ?? null;
            if (!$sku) continue;

            $mapping = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('external_sku', $sku)
                ->first();

            if (!$mapping) continue;

            $qty = (int) ($orderItem['Quantity'] ?? 1);

            DB::connection('tenant')->transaction(function () use ($mapping, $qty) {
                if ($mapping->item_variant_id) {
                    $vw = \App\Models\Tenant\ItemVariantWarehouse::where('item_variant_id', $mapping->item_variant_id)
                        ->lockForUpdate()->first();
                    if ($vw) {
                        // 1. Descontar de la hoja (fuente de verdad).
                        $vw->stock_physical = max(0, $vw->stock_physical - $qty);
                        $vw->stock = $vw->stock_physical;
                        $vw->save();

                        // 2 + 3. Recalcular los agregados derivados. Sin esto, el
                        // tenant (items.stock) y el marketplace (item_variants.stock)
                        // quedan desincronizados y se puede sobrevender.
                        // Ver skill ebaemy-stock-flow (regla de oro).
                        $variant = $vw->variant;
                        if ($variant) {
                            $variant->stock = \App\Models\Tenant\ItemVariantWarehouse::where('item_variant_id', $variant->id)
                                ->sum('stock_physical');
                            $variant->save();
                            app(\App\Services\Tenant\ItemVariantService::class)
                                ->propagateStock($variant->item);
                        }
                    }
                } else {
                    $iw = \App\Models\Tenant\ItemWarehouse::where('item_id', $mapping->item_id)
                        ->lockForUpdate()->first();
                    if ($iw) {
                        $iw->applyStockMovement(
                            \App\Enums\StockMovementTypeEnum::SALE_STORE,
                            $qty
                        );
                    }
                }
            });
        }
    }

    /**
     * RESTITUYE el stock de un pedido cancelado/devuelto (reverso de
     * processOrderStock): devuelve las unidades a stock_physical. Idempotente
     * por marketplace_orders.stock_restored_at. Sigue el flujo correcto del
     * skill ebaemy-stock-flow (variante → hoja + propagación).
     */
    public function restoreOrderStock(MarketplaceOrder $mp): bool
    {
        if ($mp->stock_restored_at) {
            return false; // ya restituido
        }

        $items = is_array($mp->items_data) ? $mp->items_data : [];
        if (isset($items['OrderItemId'])) {
            $items = [$items];
        }

        foreach ($items as $orderItem) {
            if (!is_array($orderItem)) continue;
            $sku = $orderItem['ShopSku'] ?? $orderItem['Sku'] ?? null;
            if (!$sku) continue;

            $mapping = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('external_sku', $sku)
                ->first();
            if (!$mapping) continue;

            $qty = (int) ($orderItem['Quantity'] ?? 1) ?: 1;

            DB::connection('tenant')->transaction(function () use ($mapping, $qty) {
                if ($mapping->item_variant_id) {
                    $vw = \App\Models\Tenant\ItemVariantWarehouse::where('item_variant_id', $mapping->item_variant_id)
                        ->lockForUpdate()->first();
                    if ($vw) {
                        $vw->stock_physical = $vw->stock_physical + $qty; // devuelve
                        $vw->stock = $vw->stock_physical;
                        $vw->save();

                        $variant = $vw->variant;
                        if ($variant) {
                            $variant->stock = \App\Models\Tenant\ItemVariantWarehouse::where('item_variant_id', $variant->id)
                                ->sum('stock_physical');
                            $variant->save();
                            app(\App\Services\Tenant\ItemVariantService::class)
                                ->propagateStock($variant->item);
                        }
                    }
                } else {
                    $iw = \App\Models\Tenant\ItemWarehouse::where('item_id', $mapping->item_id)
                        ->lockForUpdate()->first();
                    if ($iw) {
                        $iw->applyStockMovement(
                            \App\Enums\StockMovementTypeEnum::SALE_STORE_RETURN,
                            $qty
                        );
                    }
                }
            });
        }

        $mp->stock_restored_at = now();
        $mp->save();

        Log::channel('payments')->info('Saga: stock restituido por cancelación/devolución', [
            'marketplace_order_id' => $mp->id,
            'external_order_id'    => $mp->external_order_id,
        ]);

        return true;
    }

    // ══════════════════════════════════════════════════════════════
    // FULFILLMENT — Despacho de pedidos (vuelta a Saga)
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtiene los items de una orden de Saga (OrderItemId, estado, tracking...).
     */
    public function getOrderItems(string $externalOrderId): array
    {
        $result = $this->call('GetOrderItems', ['OrderId' => $externalOrderId]);
        $items = data_get($result, 'OrderItems.OrderItem', []);
        if (isset($items['OrderItemId'])) {
            $items = [$items];
        }
        return is_array($items) ? $items : [];
    }

    /**
     * Reconcilia el estado de los pedidos locales NO terminales contra Saga.
     *
     * fetchOrders solo CREA pedidos nuevos (filtra Status=pending) y nunca toca
     * los que ya tenía → una vez fetchado, si en Saga se despacha/cancela/entrega,
     * el estado local quedaba 'pending' para siempre. Esto consulta cada pedido
     * local pending/ready_to_ship y actualiza su estado real (y el límite de
     * despacho) desde los items de Saga.
     */
    public function reconcileOrders(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'reconcile_orders', 'pull', function ($log) {
            // Pendientes/listos (progresión de estado) + enviados/entregados de los
            // últimos 35 días (ventana de devolución de Saga) para detectar
            // cancelaciones/devoluciones y restituir el stock. Los ya cancelados
            // se excluyen.
            $locals = MarketplaceOrder::where('channel_id', $this->channel->id)
                ->where('status', '!=', 'canceled')
                ->where(function ($q) {
                    $q->whereIn('status', ['pending', 'ready_to_ship'])
                      ->orWhere('ordered_at', '>=', now()->subDays(35));
                })
                ->get();

            $checked = 0;
            $updated = 0;
            $errors = [];

            foreach ($locals as $mp) {
                try {
                    $items = $this->getOrderItems($mp->external_order_id);
                    $checked++;

                    $newStatus = $this->deriveStatusFromItems($items);
                    $dirty = false;

                    if ($newStatus && $newStatus !== $mp->status) {
                        $mp->status = $newStatus;
                        $dirty = true;
                    }

                    // Captura/actualiza el límite de despacho si Saga lo expone.
                    $deadline = null;
                    foreach ($items as $it) {
                        $deadline = $it['PromisedShippingTime'] ?? $deadline;
                    }
                    if ($deadline) {
                        $sd = is_array($mp->shipping_data) ? $mp->shipping_data : [];
                        if (($sd['promised_shipping_time'] ?? null) !== $deadline) {
                            $sd['promised_shipping_time'] = $deadline;
                            $mp->shipping_data = $sd;
                            $dirty = true;
                        }
                    }

                    if ($dirty) {
                        $mp->save();
                        // Mantiene /orders en sincronía con el estado real de Saga.
                        $mp->syncErpOrderStatus();
                        $updated++;
                    }

                    // Cancelado/devuelto → restituir stock (idempotente) y, si ya
                    // tenía boleta, marcar que falta emitir Nota de Crédito.
                    if ($newStatus === 'canceled') {
                        if ($this->restoreOrderStock($mp) && $mp->document_id && empty($mp->invoice_upload_error)) {
                            $mp->invoice_upload_error = 'DEVOLUCIÓN/CANCELACIÓN: emitir Nota de Crédito de la boleta de este pedido.';
                            $mp->save();
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = ['order' => $mp->external_order_id, 'error' => $e->getMessage()];
                }
            }

            return ['processed' => $checked, 'success' => $updated, 'failed' => count($errors), 'details' => $errors ?: null];
        })->toArray();
    }

    /**
     * Deriva el estado local de un pedido a partir del Status de sus items de Saga.
     * Toma el estado MENOS avanzado (si un item sigue pendiente, el pedido lo está).
     */
    protected function deriveStatusFromItems(array $items): ?string
    {
        // Mapa Saga → estado local. Orden = de menos a más avanzado.
        $rank = [
            'pending'        => 0,
            'ready_to_ship'  => 1,
            'shipped'        => 2,
            'delivered'      => 3,
            'canceled'       => 4,
            'cancelled'      => 4,
            'failed'         => 4,
        ];

        $statuses = [];
        foreach ($items as $it) {
            $s = strtolower(trim($it['Status'] ?? ''));
            if ($s === '') continue;
            // Cualquier variante de cancelación/fallo/devolución es terminal.
            if (str_contains($s, 'cancel') || str_contains($s, 'fail') || str_contains($s, 'return') || str_contains($s, 'reject')) {
                $s = 'canceled';
            }
            $statuses[] = $s;
        }
        if (empty($statuses)) {
            return null;
        }

        // Si TODOS están cancelados/fallidos → canceled.
        $allCanceled = collect($statuses)->every(fn($s) => ($rank[$s] ?? -1) >= 4);
        if ($allCanceled) {
            return 'canceled';
        }

        // Ignora cancelados parciales y toma el item activo MENOS avanzado.
        $active = array_filter($statuses, fn($s) => ($rank[$s] ?? 0) < 4);
        $least = collect($active)->sortBy(fn($s) => $rank[$s] ?? 0)->first();

        return match ($least) {
            'pending'       => 'pending',
            'ready_to_ship' => 'ready_to_ship',
            'shipped'       => 'shipped',
            'delivered'     => 'delivered',
            default         => null,
        };
    }

    /**
     * Marca items como LISTOS PARA DESPACHO (SetStatusToReadyToShip).
     * Para el modelo Dropshipping/Falabella, Saga ya asignó el tracking.
     */
    public function setReadyToShip(array $orderItemIds, string $deliveryType = 'dropship', string $shippingProvider = 'falabella', ?string $trackingNumber = null): array
    {
        $params = [
            'OrderItemIds' => '[' . implode(',', $orderItemIds) . ']',
            'DeliveryType' => $deliveryType,
            'ShippingProvider' => $shippingProvider,
        ];
        // En el modelo Dropshipping de Saga (ShippingProvider=falabella) la propia
        // Saga asigna el TrackingCode; reenviárselo es redundante y puede ser
        // rechazado. Solo mandamos tracking cuando el seller usa su propio courier.
        if ($trackingNumber && !$this->sagaManagesTracking($shippingProvider)) {
            $params['TrackingNumber'] = $trackingNumber;
        }
        return $this->callPost('SetStatusToReadyToShip', $params);
    }

    /**
     * ¿Saga asigna el tracking automáticamente? (modelo Falabella-managed /
     * clickandcollect). En ese caso NO debemos reenviar el TrackingNumber.
     */
    protected function sagaManagesTracking(string $shippingProvider): bool
    {
        return strtolower(trim($shippingProvider)) === 'falabella';
    }

    /**
     * Marca items como ENVIADOS (SetStatusToShipped).
     */
    public function setShipped(array $orderItemIds, string $deliveryType = 'dropship', string $shippingProvider = 'falabella', ?string $trackingNumber = null): array
    {
        $params = [
            'OrderItemIds' => '[' . implode(',', $orderItemIds) . ']',
            'DeliveryType' => $deliveryType,
            'ShippingProvider' => $shippingProvider,
        ];
        if ($trackingNumber && !$this->sagaManagesTracking($shippingProvider)) {
            $params['TrackingNumber'] = $trackingNumber;
        }
        return $this->callPost('SetStatusToShipped', $params);
    }

    /**
     * Descarga un documento de la orden (hoja de despacho, boleta/factura...).
     *
     * @param  string  $type  shippingLabel | invoice | carrierManifest
     * @return array  { mime, file (base64), filename }
     */
    public function getDocument(array $orderItemIds, string $type = 'shippingLabel'): array
    {
        $result = $this->call('GetDocument', [
            'OrderItemIds' => '[' . implode(',', $orderItemIds) . ']',
            'DocumentType' => $type,
        ]);

        return [
            'mime' => data_get($result, 'Documents.Document.MimeType', 'application/pdf'),
            'file' => data_get($result, 'Documents.Document.File'), // base64
            'type' => $type,
        ];
    }

    /**
     * Devuelve el número de comprobante que Saga ya tiene registrado para el
     * pedido (si lo hay), leyendo los items. Sirve para NO emitir una boleta
     * duplicada cuando el pedido ya fue facturado en otro sistema.
     * Tolerante con el nombre del campo (InvoiceNumber / InvoiceDocumentLink).
     */
    public function getOrderInvoiceNumber(string $externalOrderId): ?string
    {
        $items = $this->getOrderItems($externalOrderId);
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $num = $it['InvoiceNumber'] ?? null;
            if (!empty($num)) {
                return (string) $num;
            }
            // Algunos catálogos exponen solo el link del documento ya cargado.
            if (!empty($it['InvoiceDocumentLink'])) {
                return 'documento';
            }
        }
        return null;
    }

    /**
     * Marca como "boleta ya cargada" los pedidos locales que Saga reporta como
     * facturados pero que NO se emitieron desde EBAEMY (document_id null). Evita
     * volver a pedir "Generar boleta" para pedidos históricos ya cumplidos.
     */
    public function detectExternalInvoices(): array
    {
        $locals = MarketplaceOrder::where('channel_id', $this->channel->id)
            ->whereNull('invoice_uploaded_at')
            ->whereNull('document_id')
            ->get();

        $checked = 0;
        $marked = 0;
        foreach ($locals as $mp) {
            try {
                $checked++;
                $num = $this->getOrderInvoiceNumber($mp->external_order_id);
                if ($num) {
                    $mp->invoice_uploaded_at = $mp->ordered_at ?? now();
                    $mp->invoice_upload_error = 'Boleta externa en Saga: ' . $num;
                    $mp->save();
                    $marked++;
                }
            } catch (\Throwable $e) {
                // best-effort; sigue con el resto
            }
        }
        return ['processed' => $checked, 'success' => $marked, 'failed' => 0];
    }

    /**
     * Sube el comprobante (boleta) del pedido a Saga — acción SetInvoicePDF.
     * Cierra el cumplimiento que causó el delisting ("carga de comprobantes").
     *
     * @param  array   $orderItemIds  IDs de los items del pedido en Saga
     * @param  string  $invoiceNumber Número del CPE (debe coincidir, ej "B001-123")
     * @param  string  $invoiceDate   Fecha de emisión (Y-m-d)
     * @param  string  $pdfBase64     PDF de la boleta en base64
     * @param  string  $invoiceType   BOLETA | FACTURA | NOTA_DE_CREDITO
     * @see https://developers.falabella.com/v600.0.0/reference/setinvoicepdf
     */
    public function setInvoicePDF(array $orderItemIds, string $invoiceNumber, string $invoiceDate, string $pdfBase64, string $invoiceType = 'BOLETA'): array
    {
        return $this->callPostForm('SetInvoicePDF', [
            'OrderItemIds'    => '[' . implode(',', $orderItemIds) . ']',
            'InvoiceNumber'   => $invoiceNumber,
            'InvoiceDate'     => $invoiceDate,
            'InvoiceType'     => $invoiceType,
            'OperatorCode'    => 'FAPE', // Falabella Perú
            'InvoiceDocument' => $pdfBase64,
        ]);
    }

    /**
     * Variante de POST para acciones con payload grande (ej. PDF base64): firma
     * TODOS los params (incluido el documento) pero los envía en el BODY como
     * form-urlencoded para no exceder el límite de longitud del query string.
     */
    protected function callPostForm(string $action, array $params = [])
    {
        $signed = $this->signRequest($action, $params);
        $response = Http::asForm()->timeout(60)->post($this->baseUrl, $signed);

        return $this->handleResponse($action, $response);
    }

    /**
     * Avisa al vendedor por email que entró un pedido nuevo de Saga.
     * Defensivo: nunca rompe el fetch de órdenes si el email falla.
     */
    protected function notifyNewOrder(MarketplaceOrder $order): void
    {
        try {
            $establishment = \App\Models\Tenant\Establishment::first();
            $email = $establishment->email ?? null;
            $store = $establishment->name ?? 'tu tienda';
            $customer = data_get($order->customer_data, 'name', 'Cliente');
            $total = number_format((float) $order->total, 2);
            $count = is_array($order->items_data) ? count($order->items_data) : 0;

            if ($email) {
                $body = "Nuevo pedido en Saga Falabella\n\n"
                    . "Pedido #{$order->external_order_id}\n"
                    . "Cliente: {$customer}\n"
                    . "Productos: {$count}\n"
                    . "Total: S/ {$total}\n\n"
                    . "Entra a tu panel → Marketplace para marcarlo listo y descargar la hoja de despacho.";

                \Illuminate\Support\Facades\Mail::raw($body, function ($m) use ($email, $store) {
                    $m->to($email)->subject("🛒 Nuevo pedido Saga Falabella — {$store}");
                });
            }

            Log::channel('payments')->info("Nuevo pedido Saga #{$order->external_order_id} (notificado a " . ($email ?: 'sin email') . ')');
        } catch (\Throwable $e) {
            Log::channel('payments')->warning("No se pudo notificar pedido Saga: {$e->getMessage()}");
        }
    }

    // ══════════════════════════════════════════════════════════════
    // PRICES — Actualizar precios
    // ══════════════════════════════════════════════════════════════

    public function syncPrices(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'sync_prices', 'push', function ($log) {
            $mappings = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('sync_status', '!=', 'excluded')
                ->whereNotNull('external_sku')
                ->where('external_sku', '!=', '')
                ->with(['item', 'variant'])
                ->get();

            $success = 0;
            foreach ($mappings as $mapping) {
                try {
                    $price = $mapping->variant
                        ? ($mapping->variant->sale_unit_price ?: $mapping->item->sale_unit_price)
                        : $mapping->item->sale_unit_price;

                    $xml = '<?xml version="1.0" encoding="UTF-8"?><Request>'
                         . "<Product><SellerSku>{$mapping->external_sku}</SellerSku>"
                         . "<Price>" . number_format($price, 2, '.', '') . "</Price></Product>"
                         . '</Request>';

                    $this->call('ProductPriceUpdate', ['ProductData' => $xml]);
                    $success++;
                } catch (\Throwable $e) {
                    // Log but continue
                }
            }

            return ['processed' => $mappings->count(), 'success' => $success, 'failed' => $mappings->count() - $success];
        })->toArray();
    }
}
