<?php
namespace App\Http\Controllers\Tenant;

use Exception;

use App\Models\Tenant\Order;
use Illuminate\Http\Request;
use App\Models\Tenant\Series;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Establishment;
use App\Models\Tenant\ItemWarehouse;

use App\Http\Resources\Tenant\OrderCollection;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use App\Http\Resources\Tenant\ItemWarehouseCollection;
use Modules\Inventory\Models\Warehouse as ModuleWarehouse;
use App\Models\Tenant\Item;
use App\Models\Tenant\SalesChannel;
use App\Models\Tenant\Catalogs\DocumentType;
use App\Services\Tenant\OrderService;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{

  use StorageDocument;

  protected $company;

    public function index()
    {
        return view('tenant.orders.index');
    }

    public function columns()
    {
        return [
            'id' => 'Codigo de Pedido',
            'number_document' => 'Comprobante Electronico',
        ];
    }

    public function tables()
    {
      $establishments = Establishment::where('id', auth()->user()->establishment_id)->get();
      $series = collect(Series::all())->transform(function($row) {
          return [
              'id' => $row->id,
              'contingency' => (bool) $row->contingency,
              'document_type_id' => $row->document_type_id,
              'establishment_id' => $row->establishment_id,
              'number' => $row->number
          ];
      });

      $document_types = DocumentType::all();

      return compact('series', 'establishments', 'document_types');

    }

    public function item($internal_id)
    {
        $establishment_id = auth()->user()->establishment_id;
        $warehouse = ModuleWarehouse::where('establishment_id', $establishment_id)->first();

        $row = Item::where('internal_id', $internal_id)->first();

        if (!$row) {
            return response()->json(['error' => 'Producto no encontrado: ' . $internal_id], 404);
        }

        $warehouseId = $warehouse ? $warehouse->id : null;

        return [
            'id' => $row->id,
            'description' => $row->description,
            'sale_unit_price' => round($row->sale_unit_price, 2),
            'lots' => $row->item_lots->where('has_sale', false)->when($warehouseId, fn($c) => $c->where('warehouse_id', $warehouseId))->transform(function($row) {
                return [
                    'id' => $row->id,
                    'series' => $row->series,
                    'date' => $row->date,
                    'item_id' => $row->item_id,
                    'warehouse_id' => $row->warehouse_id,
                    'has_sale' => (bool)$row->has_sale,
                    'lot_code' => ($row->item_loteable_type) ? (isset($row->item_loteable->lot_code) ? $row->item_loteable->lot_code:null):null
                ];
            })->values(),
            'series_enabled' => (bool) $row->series_enabled,
            'warehouse_id'   => $warehouseId,
        ];
    }

    public function records(Request $request)
    {
        $allowedColumns = ['date_of_issue', 'id', 'shipping_address', 'reference_payment', 'total'];
        $column = in_array($request->column, $allowedColumns) ? $request->column : 'id';
        $query = Order::with('channel')->latest();

        if ($request->value) {
            $query->where($column, 'like', "%{$request->value}%");
        }

        if ($request->status_order_id) {
            $query->where('status_order_id', $request->status_order_id);
        }

        if ($request->channel_id) {
            $query->where('channel_id', $request->channel_id);
        }

        if ($request->channel_type) {
            $query->whereHas('channel', fn($q) => $q->where('type', $request->channel_type));
        }

        return new OrderCollection($query->paginate(config('tenant.items_per_page')));
    }

    public function stats()
    {
        $today      = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $total        = Order::count();
        $pending      = Order::where('status_order_id', 1)->count();
        $verified     = Order::where('status_order_id', 2)->count();
        $dispatched   = Order::where('status_order_id', 3)->count();
        $revenueMonth = Order::whereDate('created_at', '>=', $monthStart)
                             ->whereNotIn('status_order_id', [5])
                             ->sum('total');
        $revenueToday = Order::whereDate('created_at', $today)->sum('total');

        // Desglose por canal (para el dashboard)
        $byChannel = Order::selectRaw('channel_id, COUNT(*) as count, SUM(total) as revenue')
                          ->whereDate('created_at', '>=', $monthStart)
                          ->whereNotIn('status_order_id', [5])
                          ->groupBy('channel_id')
                          ->with('channel:id,name,type,code')
                          ->get()
                          ->map(fn($r) => [
                              'channel_id'   => $r->channel_id,
                              'channel_name' => $r->channel?->name ?? 'Sin canal',
                              'channel_type' => $r->channel?->type ?? 'other',
                              'count'        => (int) $r->count,
                              'revenue'      => (float) $r->revenue,
                          ]);

        return response()->json(compact('total', 'pending', 'verified', 'dispatched', 'revenueMonth', 'revenueToday', 'byChannel'));
    }

    /**
     * FASE 5 — Reporte completo de ventas por canal.
     * GET /orders/channel-report?from=2026-01-01&to=2026-03-31
     */
    public function channelReport(Request $request)
    {
        $from = $request->from ?? now()->startOfMonth()->toDateString();
        $to   = $request->to   ?? now()->toDateString();

        $channels = SalesChannel::active()->get();

        $report = $channels->map(fn($ch) => $ch->salesSummary($from, $to));

        // Totales globales para comparación
        $globalRevenue = Order::whereDate('created_at', '>=', $from)
                              ->whereDate('created_at', '<=', $to)
                              ->whereNotIn('status_order_id', [5])
                              ->sum('total');

        // Añadir porcentaje de participación
        $report = $report->map(function ($row) use ($globalRevenue) {
            $row['revenue_share'] = $globalRevenue > 0
                ? round(($row['revenue'] / $globalRevenue) * 100, 1)
                : 0;
            return $row;
        });

        return response()->json([
            'from'           => $from,
            'to'             => $to,
            'global_revenue' => (float) $globalRevenue,
            'channels'       => $report->values(),
        ]);
    }

    /**
     * Devuelve los canales activos (para filtros en el frontend).
     */
    public function channels()
    {
        return response()->json(
            SalesChannel::active()->get(['id', 'name', 'type', 'code'])
        );
    }

    /**
     * Crear pedido manual desde cualquier canal (Saga, ML, Instagram, WhatsApp, teléfono)
     */
    public function storeManual(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer',
            'customer' => 'required|array',
            'customer.name' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $channel = SalesChannel::findOrFail($request->channel_id);

        // Calcular total
        $total = 0;
        $orderItems = [];
        foreach ($request->items as $itemData) {
            $item = Item::findOrFail($itemData['item_id']);
            $price = $itemData['unit_price'] ?? $item->sale_unit_price;
            $qty = $itemData['quantity'];
            $subtotal = round($price * $qty, 2);
            $total += $subtotal;

            $orderItems[] = [
                'item_id' => $item->id,
                'description' => $item->description,
                'internal_id' => $item->internal_id,
                'quantity' => $qty,
                'unit_price' => $price,
                'sale_unit_price' => $price,
                'subtotal' => $subtotal,
                'variant_id' => $itemData['variant_id'] ?? null,
            ];
        }

        $order = Order::create([
            'external_id' => \Illuminate\Support\Str::uuid(),
            'customer' => [
                'apellidos_y_nombres_o_razon_social' => $request->customer['name'],
                'correo_electronico' => $request->customer['email'] ?? null,
                'telefono' => $request->customer['phone'] ?? null,
                'direccion' => $request->customer['address'] ?? null,
                'numero_documento' => $request->customer['document_number'] ?? null,
            ],
            'items' => $orderItems,
            'total' => $total,
            'reference_payment' => $request->reference_payment ?? $channel->name,
            'status_order_id' => 1, // Pendiente
            'channel_id' => $channel->id,
            'external_order_ref' => $request->external_order_ref, // Nro pedido Saga/ML
            'marketplace_notes' => $request->marketplace_notes,
            'warehouse_id' => $request->warehouse_id ?? $channel->warehouse_id,
            'seller_id' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pedido #{$order->id} creado desde {$channel->name}",
            'order' => $order,
        ]);
    }

    public function updateStatusOrders(Request $request)
    {
      // Despacho (status 3): delegar toda la lógica de stock al OrderService
      if ($request->record['status_order_id'] == 3) {
        $order = Order::findOrFail($request->record['id']);

        /** @var OrderService $orderService */
        $orderService = app(OrderService::class);

        // Stock dispatch + status update en la MISMA transacción
        DB::transaction(function () use ($request, $order, $orderService) {
            $orderService->processEcommerceDispatch($order, $request->discount ?? []);
            Order::where('id', $request->record['id'])->update([
                'status_order_id' => $request->record['status_order_id']
            ]);
        });

        $this->sendWhatsAppStatusNotification($order, 3);

        return [
          'message' => 'Estatus y Stock actualizado'
        ];
      }

      Order::where('id', $request->record['id'])->update(['status_order_id' => $request->record['status_order_id']]);

      $order = Order::find($request->record['id']);

      // Auto-generate SaleNote when payment is verified (status 2)
      if ($request->record['status_order_id'] == 2 && $order) {
          $autoSaleNoteService = app(\App\Services\Tenant\OrderToSaleNoteService::class);
          $autoSaleNoteService->generate($order);
      }

      // Notificación WhatsApp automática al cambiar estado
      $this->sendWhatsAppStatusNotification($order, $request->record['status_order_id']);

      // Webhook: order.status_changed
      $statusId = (int) $request->record['status_order_id'];
      $event = $statusId === 5 ? 'order.cancelled' : 'order.status_changed';
      \App\Services\Tenant\WebhookDispatcher::dispatchAsync($event, [
          'order_id'  => $order->id,
          'status_id' => $statusId,
          'total'     => $order->total,
      ]);

      return [
        'message' => 'Estatus actualizado'
      ];
    }

    public function searchWarehouse(Request $request)
    {
      $product = ItemWarehouse::whereIn('item_id', $request->item_id)->orderBy('item_id')->get();
      return new ItemWarehouseCollection($product);
    }

    /**
     * Enviar notificación WhatsApp al cliente según el nuevo estado del pedido.
     */
    private function sendWhatsAppStatusNotification(?Order $order, int $statusId): void
    {
        if (!$order) return;

        try {
            $wa = app(\App\Services\Tenant\WhatsAppService::class);
            if (!$wa->isEnabled()) return;

            $customer = $order->customer ?? [];
            $phone = $customer['telefono'] ?? null;
            $name  = $customer['apellidos_y_nombres_o_razon_social'] ?? 'Cliente';
            $orderId = str_pad($order->id, 6, '0', STR_PAD_LEFT);

            if (!$phone) return;

            match ((int) $statusId) {
                2 => $wa->send($phone, "¡Hola {$name}! ✅\n\nTu pago para el pedido *#{$orderId}* ha sido *verificado*.\nEstamos preparando tu pedido.\n\n¡Gracias por tu compra!"),
                3 => $wa->notifyClientOrderDispatched($phone, $name, $orderId),
                4 => $wa->send($phone, "¡Hola {$name}! 🚚\n\nTu pedido *#{$orderId}* está *en camino*.\n\n¡Pronto lo recibirás!"),
                6 => $wa->notifyClientOrderDelivered($phone, $name, $orderId),
                5 => $wa->send($phone, "Hola {$name},\n\nTu pedido *#{$orderId}* ha sido *cancelado*.\nSi tienes dudas, contáctanos.\n\nDisculpa las molestias."),
                default => null,
            };
        } catch (\Throwable $e) {
            \Log::warning('WhatsApp notification failed', ['order' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}

