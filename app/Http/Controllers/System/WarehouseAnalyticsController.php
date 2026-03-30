<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Analytics dashboard for SaaS admin — reads from the data warehouse.
 *
 * Only accessible by system admins (inside the system.* route group).
 * All data comes from the 'warehouse' connection — never from tenant DBs directly.
 */
class WarehouseAnalyticsController extends Controller
{
    private const DW = 'warehouse';

    public function index()
    {
        return view('system.analytics');
    }

    /**
     * Global KPIs: total tenants, total sales, active ecommerce, etc.
     */
    public function globalKpis(): array
    {
        $dw = DB::connection(self::DW);

        $ago30  = now()->subDays(30)->toDateString();
        $ago7   = now()->subDays(7)->toDateString();

        $totalTenants   = $dw->table('dw_tenant_metrics')->count();
        $activeTenants  = $dw->table('dw_tenant_metrics')
            ->where('last_sale_at', '>=', $ago30)->count();
        $sales30        = $dw->table('dw_daily_sales')
            ->where('sale_date', '>=', $ago30)->sum('net_amount');
        $sales7         = $dw->table('dw_daily_sales')
            ->where('sale_date', '>=', $ago7)->sum('net_amount');
        $withEcommerce  = $dw->table('dw_tenant_metrics')->where('has_ecommerce', true)->count();
        $withLogistic   = $dw->table('dw_tenant_metrics')->where('has_logistic', true)->count();
        $totalItems     = $dw->table('dw_tenant_metrics')->sum('total_items');
        $totalCustomers = $dw->table('dw_tenant_metrics')->sum('total_customers');

        return [
            'total_tenants'    => $totalTenants,
            'active_tenants'   => $activeTenants,    // last 30d
            'sales_30d'        => round((float) $sales30, 2),
            'sales_7d'         => round((float) $sales7, 2),
            'with_ecommerce'   => $withEcommerce,
            'with_logistic'    => $withLogistic,
            'total_items'      => (int) $totalItems,
            'total_customers'  => (int) $totalCustomers,
            'as_of'            => now()->toDateTimeString(),
        ];
    }

    /**
     * Daily sales across ALL tenants for a date range (chart data).
     * GET /system/analytics/daily-sales?from=2026-01-01&to=2026-03-24
     */
    public function dailySales(Request $request): array
    {
        $from = $request->input('from', now()->subDays(30)->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        $rows = DB::connection(self::DW)->table('dw_daily_sales')
            ->selectRaw('sale_date, SUM(net_amount) as total, SUM(cnt) as documents')
            ->whereBetween('sale_date', [$from, $to])
            ->groupBy('sale_date')
            ->orderBy('sale_date')
            ->get();

        return [
            'labels' => $rows->pluck('sale_date')->toArray(),
            'sales'  => $rows->pluck('total')->map(fn($v) => round((float)$v, 2))->toArray(),
            'docs'   => $rows->pluck('documents')->toArray(),
        ];
    }

    /**
     * Top N tenants by sales in the last 30 days.
     * GET /system/analytics/top-tenants?limit=10
     */
    public function topTenants(Request $request): array
    {
        $limit = min((int) $request->input('limit', 10), 50);

        $rows = DB::connection(self::DW)->table('dw_tenant_metrics')
            ->select([
                'tenant_hostname', 'plan_name',
                'total_items', 'total_customers',
                'sales_last_30d', 'sales_last_12m',
                'has_ecommerce', 'has_logistic', 'has_smart_stock',
                'active_items_ecommerce', 'last_sale_at',
            ])
            ->orderByDesc('sales_last_30d')
            ->limit($limit)
            ->get();

        return ['data' => $rows->toArray()];
    }

    /**
     * Sales breakdown by document type for last 30 days.
     * GET /system/analytics/by-doc-type
     */
    public function byDocType(): array
    {
        $ago30 = now()->subDays(30)->toDateString();

        $rows = DB::connection(self::DW)->table('dw_daily_sales')
            ->selectRaw('document_type, SUM(net_amount) as total, SUM(cnt) as documents')
            ->where('sale_date', '>=', $ago30)
            ->groupBy('document_type')
            ->orderByDesc('total')
            ->get();

        return ['data' => $rows->toArray()];
    }

    /**
     * Plan distribution (how many tenants per plan).
     * GET /system/analytics/plan-distribution
     */
    public function planDistribution(): array
    {
        $rows = DB::connection(self::DW)->table('dw_tenant_metrics')
            ->selectRaw('COALESCE(plan_name, "Sin plan") as plan, COUNT(*) as tenants, SUM(sales_last_30d) as sales_30d')
            ->groupBy('plan_name')
            ->orderByDesc('tenants')
            ->get();

        return ['data' => $rows->toArray()];
    }

    /**
     * ETL run history (last 20 runs).
     * GET /system/analytics/etl-log
     */
    public function etlLog(): array
    {
        $rows = DB::connection(self::DW)->table('dw_etl_log')
            ->orderByDesc('started_at')
            ->limit(20)
            ->get();

        return ['data' => $rows->toArray()];
    }
}
