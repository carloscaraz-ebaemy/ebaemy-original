<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Order;
use App\Models\Tenant\AbandonedCart;
use App\Models\Tenant\SalesChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EcommerceReportController extends Controller
{
    public function index()
    {
        return view('tenant.reports.ecommerce');
    }

    /**
     * KPIs principales del ecommerce
     */
    public function kpis(Request $request)
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to   = Carbon::parse($request->input('to', now()));

        $orders = Order::whereBetween('created_at', [$from, $to]);
        $prevFrom = (clone $from)->subDays($from->diffInDays($to));
        $prevOrders = Order::whereBetween('created_at', [$prevFrom, $from]);

        // Total ventas ecommerce
        $totalRevenue = (clone $orders)->where('status_order_id', '!=', 5)->sum('total');
        $prevRevenue  = (clone $prevOrders)->where('status_order_id', '!=', 5)->sum('total');

        // Pedidos totales
        $totalOrders = (clone $orders)->count();
        $prevTotalOrders = (clone $prevOrders)->count();

        // Ticket promedio
        $avgTicket = $totalOrders > 0 ? round($totalRevenue / $totalOrders, 2) : 0;

        // Tasa de conversión (pedidos completados / total pedidos)
        $completed = (clone $orders)->whereIn('status_order_id', [3, 4])->count();
        $conversionRate = $totalOrders > 0 ? round(($completed / $totalOrders) * 100, 1) : 0;

        // Tasa de cancelación
        $cancelled = (clone $orders)->where('status_order_id', 5)->count();
        $cancelRate = $totalOrders > 0 ? round(($cancelled / $totalOrders) * 100, 1) : 0;

        // Carritos abandonados
        $abandonedCount = AbandonedCart::whereBetween('created_at', [$from, $to])
            ->whereNull('recovered_at')->count();
        $recoveredCount = AbandonedCart::whereBetween('created_at', [$from, $to])
            ->whereNotNull('recovered_at')->count();
        $recoveryRate = ($abandonedCount + $recoveredCount) > 0
            ? round(($recoveredCount / ($abandonedCount + $recoveredCount)) * 100, 1) : 0;

        return response()->json([
            'period'           => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'total_revenue'    => $totalRevenue,
            'prev_revenue'     => $prevRevenue,
            'revenue_change'   => $prevRevenue > 0 ? round((($totalRevenue - $prevRevenue) / $prevRevenue) * 100, 1) : 0,
            'total_orders'     => $totalOrders,
            'prev_orders'      => $prevTotalOrders,
            'avg_ticket'       => $avgTicket,
            'conversion_rate'  => $conversionRate,
            'cancel_rate'      => $cancelRate,
            'abandoned_carts'  => $abandonedCount,
            'recovered_carts'  => $recoveredCount,
            'recovery_rate'    => $recoveryRate,
            'completed_orders' => $completed,
        ]);
    }

    /**
     * Ventas por día (gráfico de línea)
     */
    public function dailySales(Request $request)
    {
        $from = Carbon::parse($request->input('from', now()->subDays(30)));
        $to   = Carbon::parse($request->input('to', now()));

        $data = Order::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(CASE WHEN status_order_id != 5 THEN total ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN status_order_id = 5 THEN 1 ELSE 0 END) as cancelled')
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json(['daily_sales' => $data]);
    }

    /**
     * Top productos más vendidos en ecommerce
     */
    public function topProducts(Request $request)
    {
        $from  = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to    = Carbon::parse($request->input('to', now()));
        $limit = $request->input('limit', 20);

        // Orders store items as JSON, so we need to decode
        $orders = Order::whereBetween('created_at', [$from, $to])
            ->where('status_order_id', '!=', 5)
            ->get(['items', 'total']);

        $products = collect();
        foreach ($orders as $order) {
            $items = is_array($order->items) ? $order->items : json_decode($order->items, true);
            if (!is_array($items)) continue;
            foreach ($items as $item) {
                $key = $item['item_id'] ?? $item['id'] ?? 'unknown';
                $existing = $products->get($key, [
                    'item_id'     => $key,
                    'description' => $item['item']['description'] ?? $item['description'] ?? 'N/A',
                    'quantity'    => 0,
                    'revenue'     => 0,
                    'orders'      => 0,
                ]);
                $existing['quantity'] += $item['quantity'] ?? 0;
                $existing['revenue']  += ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                $existing['orders']   += 1;
                $products->put($key, $existing);
            }
        }

        $sorted = $products->sortByDesc('revenue')->take($limit)->values();

        return response()->json(['top_products' => $sorted]);
    }

    /**
     * Ventas por canal
     */
    public function channelBreakdown(Request $request)
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to   = Carbon::parse($request->input('to', now()));

        $data = Order::select(
                'channel_id',
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(CASE WHEN status_order_id != 5 THEN total ELSE 0 END) as revenue'),
                DB::raw('AVG(CASE WHEN status_order_id != 5 THEN total END) as avg_ticket')
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('channel_id')
            ->get()
            ->map(function ($row) {
                $channel = $row->channel_id ? SalesChannel::find($row->channel_id) : null;
                return [
                    'channel_id'   => $row->channel_id,
                    'channel_name' => $channel->name ?? 'Sin canal',
                    'channel_type' => $channel->type ?? 'unknown',
                    'orders'       => $row->orders,
                    'revenue'      => round($row->revenue, 2),
                    'avg_ticket'   => round($row->avg_ticket, 2),
                ];
            });

        $globalRevenue = $data->sum('revenue');
        $data = $data->map(function ($row) use ($globalRevenue) {
            $row['revenue_share'] = $globalRevenue > 0
                ? round(($row['revenue'] / $globalRevenue) * 100, 1) : 0;
            return $row;
        });

        return response()->json(['channels' => $data->values()]);
    }

    /**
     * Análisis de carritos abandonados
     */
    public function abandonedCartAnalysis(Request $request)
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to   = Carbon::parse($request->input('to', now()));

        $total    = AbandonedCart::whereBetween('created_at', [$from, $to])->count();
        $active   = AbandonedCart::whereBetween('created_at', [$from, $to])->active()->count();
        $expired  = AbandonedCart::whereBetween('created_at', [$from, $to])->expired()->count();
        $recovered = AbandonedCart::whereBetween('created_at', [$from, $to])
            ->whereNotNull('recovered_at')->count();
        $reminded = AbandonedCart::whereBetween('created_at', [$from, $to])
            ->whereNotNull('reminder_sent_at')->count();

        $lostRevenue = AbandonedCart::whereBetween('created_at', [$from, $to])
            ->whereNull('recovered_at')
            ->sum('subtotal');

        $recoveredRevenue = AbandonedCart::whereBetween('created_at', [$from, $to])
            ->whereNotNull('recovered_at')
            ->sum('subtotal');

        // Daily trend
        $daily = AbandonedCart::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN recovered_at IS NOT NULL THEN 1 ELSE 0 END) as recovered')
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'total'              => $total,
            'active'             => $active,
            'expired'            => $expired,
            'recovered'          => $recovered,
            'reminded'           => $reminded,
            'recovery_rate'      => $total > 0 ? round(($recovered / $total) * 100, 1) : 0,
            'lost_revenue'       => round($lostRevenue, 2),
            'recovered_revenue'  => round($recoveredRevenue, 2),
            'daily_trend'        => $daily,
        ]);
    }

    /**
     * Ventas por hora del día
     */
    public function salesByHour(Request $request)
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()));
        $to   = Carbon::parse($request->input('to', now()));

        $data = Order::select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as orders'),
                DB::raw('SUM(CASE WHEN status_order_id != 5 THEN total ELSE 0 END) as revenue')
            )
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderBy('hour')
            ->get();

        return response()->json(['sales_by_hour' => $data]);
    }

    /**
     * Customer Lifetime Value promedio
     */
    public function customerLtv(Request $request)
    {
        $limit = $request->input('limit', 20);

        $data = Order::select(
                'person_id',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(CASE WHEN status_order_id != 5 THEN total ELSE 0 END) as lifetime_value'),
                DB::raw('AVG(CASE WHEN status_order_id != 5 THEN total END) as avg_ticket'),
                DB::raw('MIN(created_at) as first_purchase'),
                DB::raw('MAX(created_at) as last_purchase')
            )
            ->whereNotNull('person_id')
            ->groupBy('person_id')
            ->orderByDesc('lifetime_value')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $person = \App\Models\Tenant\Person::find($row->person_id);
                return [
                    'person_id'      => $row->person_id,
                    'name'           => $person->name ?? 'N/A',
                    'total_orders'   => $row->total_orders,
                    'lifetime_value' => round($row->lifetime_value, 2),
                    'avg_ticket'     => round($row->avg_ticket, 2),
                    'first_purchase' => $row->first_purchase,
                    'last_purchase'  => $row->last_purchase,
                ];
            });

        return response()->json(['top_customers' => $data]);
    }
}
