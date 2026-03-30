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
            $mappings = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('sync_status', 'synced')
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

                    MarketplaceOrder::create([
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
                        ],
                        'total' => (float) ($orderData['Price'] ?? 0),
                        'ordered_at' => $orderData['CreatedAt'] ?? now(),
                    ]);

                    // Descontar stock en ERP
                    $this->processOrderStock($orderItems);

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
                        $vw->stock_physical = max(0, $vw->stock_physical - $qty);
                        $vw->stock = $vw->stock_physical;
                        $vw->save();
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

    // ══════════════════════════════════════════════════════════════
    // PRICES — Actualizar precios
    // ══════════════════════════════════════════════════════════════

    public function syncPrices(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'sync_prices', 'push', function ($log) {
            $mappings = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('sync_status', 'synced')
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
