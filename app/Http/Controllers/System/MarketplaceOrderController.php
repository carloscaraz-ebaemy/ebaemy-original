<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceOrder;
use App\Models\System\TenantMarketplaceOrder;
use App\Services\System\MarketplaceMultiOrderDispatcher;
use Illuminate\Http\Request;

/**
 * Panel SuperAdmin para gestionar pedidos multi-tienda creados desde
 * ebaemy.com/marketplace. Permite:
 *   - Listar con filtros por status/tienda/fecha
 *   - Ver detalle con subpedidos por tienda y errores de dispatch
 *   - Reintentar el dispatch de subpedidos failed
 *   - Cancelar subpedidos / pedidos
 */
class MarketplaceOrderController extends Controller
{
    public function __construct(private MarketplaceMultiOrderDispatcher $dispatcher) {}

    public function index(Request $request)
    {
        $stats = [
            'total'                => MarketplaceOrder::count(),
            'pending'              => MarketplaceOrder::where('status', 'pending')->count(),
            'partially_confirmed'  => MarketplaceOrder::where('status', 'partially_confirmed')->count(),
            'confirmed'            => MarketplaceOrder::where('status', 'confirmed')->count(),
            'partially_cancelled'  => MarketplaceOrder::where('status', 'partially_cancelled')->count(),
            'cancelled'            => MarketplaceOrder::where('status', 'cancelled')->count(),
            'failed_dispatches'    => TenantMarketplaceOrder::where('status', 'failed')->count(),
            'last_24h'             => MarketplaceOrder::where('created_at', '>=', now()->subDay())->count(),
        ];

        return view('system.marketplace_orders.index', compact('stats'));
    }

    /**
     * JSON paginado para la vista index.
     */
    public function records(Request $request)
    {
        $status = $request->input('status');
        $q      = $request->input('q');
        $page   = max(1, (int) $request->input('page', 1));
        $per    = min(100, max(10, (int) $request->input('per_page', 25)));

        $query = MarketplaceOrder::query()
            ->withCount('tenantOrders')
            ->withCount(['tenantOrders as failed_count' => fn ($q) => $q->where('status', 'failed')])
            ->orderByDesc('created_at');

        if ($status) {
            $query->where('status', $status);
        }
        if ($q) {
            $query->where(function ($w) use ($q) {
                $w->where('order_number', 'like', "%{$q}%")
                  ->orWhere('customer_name', 'like', "%{$q}%")
                  ->orWhere('customer_phone', 'like', "%{$q}%")
                  ->orWhere('customer_email', 'like', "%{$q}%");
            });
        }

        $paginator = $query->paginate($per, ['*'], 'page', $page);

        return [
            'data'  => $paginator->items(),
            'total' => $paginator->total(),
            'page'  => $paginator->currentPage(),
            'last'  => $paginator->lastPage(),
        ];
    }

    public function show(int $id)
    {
        $order = MarketplaceOrder::query()
            ->with(['items', 'tenantOrders.client'])
            ->findOrFail($id);

        $itemsByStore = $order->items->groupBy('hostname_id');

        return view('system.marketplace_orders.show', compact('order', 'itemsByStore'));
    }

    /**
     * Reintenta el dispatch del pedido completo. El dispatcher sólo procesa
     * subpedidos en status=pending; los failed los pasamos primero a pending
     * para que entren al ciclo (reset retry no — preservamos historial).
     */
    public function retry(int $id)
    {
        $order = MarketplaceOrder::query()->findOrFail($id);

        TenantMarketplaceOrder::query()
            ->where('marketplace_order_id', $order->id)
            ->where('status', 'failed')
            ->update(['status' => 'pending']);

        $result = $this->dispatcher->dispatchOrder($order->refresh());

        return [
            'success'  => true,
            'message'  => "Reintento ejecutado: {$result['success_count']} OK, {$result['failed_count']} fallaron.",
            'dispatch' => $result,
        ];
    }

    /**
     * Reintenta el dispatch de UN subpedido (tenant_marketplace_order)
     * específico. Útil si una tienda ya está disponible y otras siguen
     * fallando.
     */
    public function retrySubOrder(int $orderId, int $subId)
    {
        $sub = TenantMarketplaceOrder::query()
            ->where('marketplace_order_id', $orderId)
            ->where('id', $subId)
            ->firstOrFail();

        if ($sub->status === 'dispatched' || $sub->status === 'delivered') {
            return [
                'success' => false,
                'message' => 'Este subpedido ya está despachado.',
            ];
        }

        $sub->update(['status' => 'pending']);

        $order = $sub->marketplaceOrder;
        $result = $this->dispatcher->dispatchOrder($order);

        return [
            'success'  => true,
            'message'  => 'Reintento subpedido ejecutado.',
            'dispatch' => $result,
        ];
    }

    /**
     * Cancela el pedido completo (todos los subpedidos no dispatched). Para
     * subpedidos ya dispatched se mantiene el estado — el tenant ya tiene
     * la Order y debe gestionarla allí.
     */
    public function cancel(int $id)
    {
        $order = MarketplaceOrder::query()->findOrFail($id);

        TenantMarketplaceOrder::query()
            ->where('marketplace_order_id', $order->id)
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'cancelled']);

        $order->refresh();
        $order->recomputeStatus();

        return [
            'success' => true,
            'message' => 'Pedido cancelado (subpedidos pendientes/fallidos marcados como cancelled).',
        ];
    }
}
