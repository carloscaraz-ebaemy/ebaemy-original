<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\MarketplaceOrder;
use App\Models\Tenant\MarketplaceSyncLog;
use App\Models\Tenant\Item;
use App\Services\Marketplace\MarketplaceOrchestrator;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    public function index()
    {
        return view('ecommerce::configuration.marketplace');
    }

    public function productsByChannel()
    {
        return view('ecommerce::configuration.marketplace_products');
    }

    /**
     * Guardar asignación masiva de productos a un canal.
     */
    public function saveChannelProducts(Request $request, int $channelId)
    {
        $items = $request->input('items', []);
        $saved = 0;
        $removed = 0;

        foreach ($items as $item) {
            $itemId = $item['item_id'] ?? null;
            if (!$itemId) continue;

            if ($item['active'] ?? false) {
                MarketplaceProduct::updateOrCreate(
                    ['channel_id' => $channelId, 'item_id' => $itemId],
                    [
                        'external_sku' => $item['external_sku'] ?? null,
                        'sync_status'  => 'pending',
                    ]
                );
                $saved++;
            } else {
                MarketplaceProduct::where('channel_id', $channelId)
                    ->where('item_id', $itemId)
                    ->delete();
                $removed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$saved} productos asignados, {$removed} removidos",
        ]);
    }

    /**
     * Convertir un pedido de marketplace en un Order regular.
     * Así aparece en la lista de Pedidos junto con los del ecommerce.
     */
    public function convertToOrder(int $marketplaceOrderId)
    {
        $mpOrder = MarketplaceOrder::findOrFail($marketplaceOrderId);

        if ($mpOrder->order_id) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido ya fue convertido (Order #' . $mpOrder->order_id . ')',
            ], 422);
        }

        // Buscar el canal de venta correspondiente al marketplace
        $channel = $mpOrder->channel;
        $salesChannel = \App\Models\Tenant\SalesChannel::where('type', 'marketplace')
            ->where('name', 'LIKE', '%' . ($channel->platform ?? $channel->name) . '%')
            ->first();

        $channelId = $salesChannel->id ?? null;
        $warehouseId = $salesChannel->warehouse_id ?? \Modules\Inventory\Models\Warehouse::first()->id ?? null;

        // Crear Order regular
        $order = \App\Models\Tenant\Order::create([
            'external_id'      => \Illuminate\Support\Str::uuid()->toString(),
            'customer'         => $mpOrder->customer_data ?? [],
            'items'            => $mpOrder->items_data ?? [],
            'total'            => $mpOrder->total,
            'shipping_address' => $mpOrder->shipping_data['address'] ?? 'Marketplace',
            'status_order_id'  => 1,
            'reference_payment'=> 'marketplace_' . ($channel->platform ?? 'unknown'),
            'channel_id'       => $channelId,
            'warehouse_id'     => $warehouseId,
            'marketplace_notes'=> 'Pedido externo #' . $mpOrder->external_order_id . ' de ' . ($channel->name ?? $channel->platform),
            'purchase'         => [],
        ]);

        // Vincular marketplace_order con el order
        $mpOrder->order_id = $order->id;
        $mpOrder->status = 'processed';
        $mpOrder->processed_at = now();
        $mpOrder->save();

        return response()->json([
            'success' => true,
            'message' => 'Pedido convertido a Order #' . $order->id,
            'order_id' => $order->id,
        ]);
    }

    // ── Channels CRUD ──────────────────────────────────────────

    public function channels()
    {
        return response()->json(MarketplaceChannel::latest()->get());
    }

    public function storeChannel(Request $request)
    {
        $request->validate([
            'platform' => 'required|in:falabella,meta,mercadolibre',
            'name' => 'required|string|max:100',
        ]);

        $channel = MarketplaceChannel::create([
            'platform' => $request->platform,
            'name' => $request->name,
            'credentials' => $request->credentials ?? [],
            'settings' => $request->settings ?? ['auto_sync' => true, 'sync_interval' => 30],
        ]);

        return response()->json(['success' => true, 'channel' => $channel]);
    }

    public function updateChannel(Request $request, int $id)
    {
        $channel = MarketplaceChannel::findOrFail($id);
        $channel->update($request->only(['name', 'status', 'credentials', 'settings']));
        return response()->json(['success' => true]);
    }

    // ── Product Mapping ────────────────────────────────────────

    public function products(int $channelId)
    {
        $products = MarketplaceProduct::where('channel_id', $channelId)
            ->with(['item:id,description,internal_id,sale_unit_price,stock', 'variant:id,display_name,sku,stock'])
            ->paginate(50);

        return response()->json($products);
    }

    public function mapProduct(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer',
            'item_id' => 'required|integer',
            'external_sku' => 'required|string|max:100',
        ]);

        $mapping = MarketplaceProduct::updateOrCreate(
            [
                'channel_id' => $request->channel_id,
                'item_id' => $request->item_id,
                'item_variant_id' => $request->item_variant_id,
            ],
            [
                'external_sku' => $request->external_sku,
                'sync_status' => 'pending',
            ]
        );

        return response()->json(['success' => true, 'mapping' => $mapping]);
    }

    public function autoMapProducts(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);

        // Auto-mapear: SKU interno = SKU externo para items con apply_store=1
        $items = Item::where('apply_store', 1)->whereNotNull('internal_id')->get();
        $mapped = 0;

        foreach ($items as $item) {
            if ($item->has_variants && $item->variants->count() > 0) {
                foreach ($item->variants->where('is_active', true) as $variant) {
                    MarketplaceProduct::firstOrCreate([
                        'channel_id' => $channelId,
                        'item_id' => $item->id,
                        'item_variant_id' => $variant->id,
                    ], [
                        'external_sku' => $variant->sku ?: "{$item->internal_id}-V{$variant->id}",
                        'sync_status' => 'pending',
                    ]);
                    $mapped++;
                }
            } else {
                MarketplaceProduct::firstOrCreate([
                    'channel_id' => $channelId,
                    'item_id' => $item->id,
                ], [
                    'external_sku' => $item->internal_id,
                    'sync_status' => 'pending',
                ]);
                $mapped++;
            }
        }

        return response()->json(['success' => true, 'mapped' => $mapped]);
    }

    // ── Sync Actions ───────────────────────────────────────────

    public function syncProducts(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'syncProducts')) {
            return response()->json(['error' => 'Service not available'], 400);
        }

        $result = $service->syncProducts();
        return response()->json($result);
    }

    public function syncStock(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'syncStock')) {
            return response()->json(['error' => 'Service not available'], 400);
        }

        $result = $service->syncStock();
        return response()->json($result);
    }

    public function fetchOrders(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'fetchOrders')) {
            return response()->json(['error' => 'Service not available'], 400);
        }

        $result = $service->fetchOrders();
        return response()->json($result);
    }

    // ── Orders ─────────────────────────────────────────────────

    public function orders(Request $request)
    {
        $orders = MarketplaceOrder::with('channel:id,platform,name')
            ->when($request->channel_id, fn($q) => $q->where('channel_id', $request->channel_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($orders);
    }

    // ── Logs ───────────────────────────────────────────────────

    public function logs(Request $request)
    {
        $logs = MarketplaceSyncLog::with('channel:id,platform,name')
            ->when($request->channel_id, fn($q) => $q->where('channel_id', $request->channel_id))
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($logs);
    }

    // ── Meta Feed ──────────────────────────────────────────────

    public function generateFeed(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);

        if ($channel->platform !== 'meta') {
            return response()->json(['error' => 'Feed only available for Meta channels'], 400);
        }

        $service = new \App\Services\Marketplace\MetaFeedService($channel);
        $service->generateXmlFeed();
        $service->generateCsvFeed();

        return response()->json([
            'success' => true,
            'feeds' => [
                'xml' => asset('storage/feeds/meta-catalog.xml'),
                'csv' => asset('storage/feeds/meta-catalog.csv'),
            ],
        ]);
    }
}
