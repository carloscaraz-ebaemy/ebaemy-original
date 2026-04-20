<?php
namespace App\Http\Controllers\Tenant;

use Exception;

use App\Exceptions\InvalidOrderTransitionException;
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
use App\Models\Tenant\OrderStatusLog;
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
      // NOTA: `exists:orders,id` removido — en multi-tenant la regla usa la
      // conexión default (system) donde `orders` no existe, generando un 500.
      // El `findOrFail` de abajo ya valida la existencia en la conexión tenant.
      $validated = $request->validate([
        'record.id' => 'required|integer|min:1',
        'record.status_order_id' => 'required|integer|in:1,2,3,4,5,6',
      ]);

      $orderId = (int) data_get($validated, 'record.id');
      $statusId = (int) data_get($validated, 'record.status_order_id');

      try {
          $order = Order::findOrFail($orderId);
      } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
          return response()->json(['message' => "Pedido #{$orderId} no encontrado"], 404);
      }

      $currentStatusId = (int) $order->status_order_id;
      if ($currentStatusId === $statusId) {
        return [
          'message' => 'El pedido ya se encuentra en ese estado'
        ];
      }

      // Delegamos TODAS las reglas de transición (mapa + guard de payment_status +
      // reglas por rol) al OrderPolicy::transitionTo. Si la transición es inválida
      // lanza InvalidOrderTransitionException con mensaje específico.
      try {
          $this->authorize('transitionTo', [$order, $statusId]);
      } catch (\App\Exceptions\InvalidOrderTransitionException $e) {
          return response()->json(['message' => $e->getMessage()], 422);
      } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
          return response()->json(['message' => $e->getMessage() ?: 'No autorizado para esta acción'], 403);
      }

      /** @var OrderService $orderService */
      $orderService = app(OrderService::class);
      $discountItems = $request->discount ?? [];

      try {
      // ── 2 → 3  (En preparación) ──────────────────────────────────────────
      // Flujo nuevo: solo marca prepared_at, no toca stock (ya reservado en checkout).
      // Retrocompat: si el UI envía `discount` (legacy), se despacha físico aquí mismo
      // y queda también marcado dispatched_at para evitar doble descuento en 3→4.
      if ($statusId === 3) {
        if (!empty($discountItems)) {
          $orderService->processEcommerceDispatch($order, $discountItems);
          $this->logStatusTransition($order->fresh(), $currentStatusId, 3, ['discount' => $discountItems, 'mode' => 'legacy']);
          $this->sendWhatsAppStatusNotification($order->fresh(), 3);
          return ['message' => 'Estatus y Stock actualizado'];
        }

        $orderService->prepareEcommerceOrder($order);
        $this->logStatusTransition($order->fresh(), $currentStatusId, 3, ['mode' => 'prepare']);
        $this->sendWhatsAppStatusNotification($order->fresh(), 3);
        return ['message' => 'Pedido marcado como en preparación'];
      }

      // ── 3 → 4  (Despachado / Enviado) ────────────────────────────────────
      // Descuento físico real. Idempotente: si ya se hizo en 2→3 (legacy),
      // solo actualiza el estado sin volver a descontar stock.
      if ($statusId === 4) {
        $orderService->dispatchEcommerceOrder($order, $discountItems);
        $this->logStatusTransition($order->fresh(), $currentStatusId, 4, ['discount' => $discountItems]);
        $this->sendWhatsAppStatusNotification($order->fresh(), 4);

        \App\Services\Tenant\WebhookDispatcher::dispatchAsync('order.status_changed', [
            'order_id'  => $order->id,
            'status_id' => 4,
            'total'     => $order->total,
        ]);

        return ['message' => 'Pedido despachado'];
      }

      // ── 4 → 6  (Entregado) ───────────────────────────────────────────────
      if ($statusId === 6) {
        $orderService->markEcommerceDelivered($order);
        $this->logStatusTransition($order->fresh(), $currentStatusId, 6, []);
        $this->sendWhatsAppStatusNotification($order->fresh(), 6);

        \App\Services\Tenant\WebhookDispatcher::dispatchAsync('order.status_changed', [
            'order_id'  => $order->id,
            'status_id' => 6,
            'total'     => $order->total,
        ]);

        return ['message' => 'Pedido entregado'];
      }

      // ── * → 5  (Cancelado) ───────────────────────────────────────────────
      // Libera stock_committed si el pedido todavía no fue despachado.
      if ($statusId === 5) {
        $reason = (string) $request->input('cancel_reason', '');
        $orderService->cancelEcommerceOrder($order, $reason);
        $this->logStatusTransition($order->fresh(), $currentStatusId, 5, ['reason' => $reason]);
        $this->sendWhatsAppStatusNotification($order->fresh(), 5);

        \App\Services\Tenant\WebhookDispatcher::dispatchAsync('order.cancelled', [
            'order_id'  => $order->id,
            'status_id' => 5,
            'total'     => $order->total,
            'reason'    => $reason,
        ]);

        return ['message' => 'Pedido cancelado'];
      }

      // ── 1 → 2  (Pago verificado) ─────────────────────────────────────────
      Order::where('id', $orderId)->update(['status_order_id' => $statusId]);
      $order->status_order_id = $statusId;

      if ($statusId === 2 && $order) {
          $autoSaleNoteService = app(\App\Services\Tenant\OrderToSaleNoteService::class);
          $autoSaleNoteService->generate($order);
      }

      $this->logStatusTransition($order, $currentStatusId, $statusId, []);
      $this->sendWhatsAppStatusNotification($order, $statusId);

      \App\Services\Tenant\WebhookDispatcher::dispatchAsync('order.status_changed', [
          'order_id'  => $order->id,
          'status_id' => $statusId,
          'total'     => $order->total,
      ]);

      return [
        'message' => 'Estatus actualizado'
      ];
      } catch (\App\Exceptions\InsufficientStockException $e) {
          return response()->json(['message' => $e->getMessage()], 422);
      } catch (\App\Exceptions\InvalidOrderTransitionException $e) {
          return response()->json(['message' => $e->getMessage()], 422);
      } catch (\Throwable $e) {
          \Log::error('[updateStatusOrders] unexpected error', [
              'order_id'   => $orderId,
              'status_id'  => $statusId,
              'from'       => $currentStatusId,
              'discount'   => $discountItems,
              'exception'  => get_class($e),
              'message'    => $e->getMessage(),
              'file'       => $e->getFile() . ':' . $e->getLine(),
              'trace'      => collect($e->getTrace())->take(8)->map(fn($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['class'] ?? '') . ($f['type'] ?? '') . ($f['function'] ?? ''))->all(),
          ]);
          return response()->json([
              'message' => 'Error al actualizar el estado: ' . $e->getMessage(),
              'exception' => get_class($e),
          ], 500);
      }
    }

    /**
     * GET /orders/{order}/status-logs
     * Devuelve el historial de transiciones del pedido para renderizar timeline.
     */
    public function statusLogs($orderId)
    {
        $order = Order::findOrFail((int) $orderId);

        $labels = [
            1 => 'Pendiente',
            2 => 'Pago verificado',
            3 => 'En preparación',
            4 => 'Despachado',
            5 => 'Cancelado',
            6 => 'Entregado',
        ];

        $logs = OrderStatusLog::where('order_id', $order->id)
            ->with('actor:id,name,email')
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($log) use ($labels) {
                return [
                    'id'               => $log->id,
                    'from_status'      => $log->from_status,
                    'from_label'       => $labels[$log->from_status] ?? null,
                    'to_status'        => $log->to_status,
                    'to_label'         => $labels[$log->to_status] ?? null,
                    'payment_status'   => $log->payment_status,
                    'actor'            => $log->actor ? [
                        'id'    => $log->actor->id,
                        'name'  => $log->actor->name,
                        'email' => $log->actor->email,
                    ] : null,
                    'payload'          => $log->payload,
                    'created_at'       => $log->created_at?->format('Y-m-d H:i:s'),
                    'created_at_human' => $log->created_at?->diffForHumans(),
                ];
            });

        return response()->json([
            'order_id' => $order->id,
            'current_status' => [
                'id'    => (int) $order->status_order_id,
                'label' => $labels[(int) $order->status_order_id] ?? null,
            ],
            'payment_status' => $order->payment_status,
            'phases' => [
                'prepared_at'   => optional($order->prepared_at)->format('Y-m-d H:i:s'),
                'dispatched_at' => optional($order->dispatched_at)->format('Y-m-d H:i:s'),
                'delivered_at'  => optional($order->delivered_at)->format('Y-m-d H:i:s'),
            ],
            'logs' => $logs,
        ]);
    }

    /**
     * Registra una transición de estado en `order_status_logs`.
     * Falla silenciosa (solo log en canal laravel) — el audit trail
     * no debe romper operaciones de negocio.
     */
    private function logStatusTransition(?Order $order, int $from, int $to, array $payload = []): void
    {
        if (!$order) return;
        try {
            OrderStatusLog::create([
                'order_id'       => $order->id,
                'from_status'    => $from,
                'to_status'      => $to,
                'payment_status' => $order->payment_status,
                'actor_id'       => auth()->id(),
                'payload'        => $payload ?: null,
                'created_at'     => now(),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('Failed to write OrderStatusLog', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }
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

