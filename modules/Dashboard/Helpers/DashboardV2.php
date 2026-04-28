<?php

namespace Modules\Dashboard\Helpers;

use App\Services\Tenant\ReplicaConnectionManager;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\SaleNoteItem;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentItem;
use App\Models\Tenant\Purchase;
use App\Models\Tenant\ItemWarehouse;

/**
 * DashboardV2 — Queries optimizadas usando Eloquent para respetar
 * la conexión del tenant (Hyn tenancy). DB::table() usaría el landlord.
 */
class DashboardV2
{
    private array $validStates = ['01', '03', '05', '07', '13'];
    private ?int  $establishmentId;

    public function __construct(?int $establishmentId = null)
    {
        $this->establishmentId = $establishmentId;
    }

    // ── Shortcut: query builder sobre la réplica (o primaria como fallback) ─
    // Usa ReplicaConnectionManager: si TENANT_REPLICA_HOST está definido,
    // todas las queries del dashboard van a la réplica de solo-lectura.
    private function replica(): ReplicaConnectionManager
    {
        return app(ReplicaConnectionManager::class);
    }

    private function snQuery()       { return $this->replica()->queryFor(SaleNote::class); }
    private function docQuery()      { return $this->replica()->queryFor(Document::class); }
    private function snItemQuery()   { return $this->replica()->queryFor(SaleNoteItem::class); }
    private function docItemQuery()  { return $this->replica()->queryFor(DocumentItem::class); }
    private function purchaseQuery() { return $this->replica()->queryFor(Purchase::class); }

    // ── Conexión del tenant para joins personalizados ──────────────────────
    private function tenantDb()
    {
        return $this->replica()->connection();
    }

    // ─────────────────────────────────────────────────────────────────────
    //  SUMMARY — todos los KPIs en una llamada (caché 10 min)
    // ─────────────────────────────────────────────────────────────────────
    public function summary(): array
    {
        $cacheKey = "dash_v2_summary_{$this->establishmentId}";

        return Cache::remember($cacheKey, 600, function () {
            $today  = Carbon::today()->toDateString();
            $mStart = Carbon::now()->startOfMonth()->toDateString();
            $mEnd   = Carbon::now()->endOfMonth()->toDateString();
            $yStart = Carbon::now()->startOfYear()->toDateString();
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            // ── Notas de venta ──────────────────────────────────────
            $snToday = $this->snQuery()
                ->whereIn('state_type_id', $states)
                ->whereDate('date_of_issue', $today)
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
                ->first();

            $snMonth = $this->snQuery()
                ->whereIn('state_type_id', $states)
                ->whereBetween('date_of_issue', [$mStart, $mEnd])
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
                ->first();

            $snYear = $this->snQuery()
                ->whereIn('state_type_id', $states)
                ->where('date_of_issue', '>=', $yStart)
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
                ->first();

            // ── Comprobantes (CPE) ──────────────────────────────────
            $docToday = $this->docQuery()
                ->whereIn('state_type_id', $states)
                ->whereDate('date_of_issue', $today)
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
                ->first();

            $docMonth = $this->docQuery()
                ->whereIn('state_type_id', $states)
                ->whereBetween('date_of_issue', [$mStart, $mEnd])
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
                ->first();

            // ── Utilidad estimada mes (NV) ──────────────────────────
            $utilNV = $this->snItemQuery()
                ->join('sale_notes as sn', 'sn.id', '=', 'sale_note_items.sale_note_id')
                ->whereIn('sn.state_type_id', $states)
                ->whereBetween('sn.date_of_issue', [$mStart, $mEnd])
                ->when($estId, fn($q) => $q->where('sn.establishment_id', $estId))
                ->selectRaw("COALESCE(SUM(
                    sale_note_items.quantity * (
                        sale_note_items.unit_price -
                        COALESCE(CAST(JSON_UNQUOTE(
                            JSON_EXTRACT(sale_note_items.item,'$.purchase_unit_price')
                        ) AS DECIMAL(15,4)), 0)
                    )
                ), 0) as utility")
                ->first();

            // ── Utilidad estimada mes (CPE) ─────────────────────────
            $utilDoc = $this->docItemQuery()
                ->join('documents as d', 'd.id', '=', 'document_items.document_id')
                ->whereIn('d.state_type_id', $states)
                ->whereBetween('d.date_of_issue', [$mStart, $mEnd])
                ->when($estId, fn($q) => $q->where('d.establishment_id', $estId))
                ->selectRaw("COALESCE(SUM(
                    document_items.quantity * (
                        document_items.unit_price -
                        COALESCE(CAST(JSON_UNQUOTE(
                            JSON_EXTRACT(document_items.item,'$.purchase_unit_price')
                        ) AS DECIMAL(15,4)), 0)
                    )
                ), 0) as utility")
                ->first();

            // ── Compras del mes ─────────────────────────────────────
            $purchases = $this->purchaseQuery()
                ->whereIn('state_type_id', $states)
                ->whereBetween('date_of_issue', [$mStart, $mEnd])
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
                ->first();

            // ── Totales ─────────────────────────────────────────────
            $salesToday = round((float)$snToday->total + (float)$docToday->total, 2);
            $salesMonth = round((float)$snMonth->total + (float)$docMonth->total, 2);
            $salesYear  = round((float)$snYear->total, 2);
            $cntMonth   = (int)$snMonth->cnt + (int)$docMonth->cnt;
            $utility    = round((float)$utilNV->utility + (float)$utilDoc->utility, 2);
            $avgTicket  = $cntMonth > 0 ? round($salesMonth / $cntMonth, 2) : 0;

            // ── Ecommerce orders (today) ─────────────────────────
            $ecomOrdersToday = \App\Models\Tenant\Order::whereDate('created_at', $today)
                ->where('status_order_id', '!=', 5)
                ->count();
            $ecomRevenueToday = \App\Models\Tenant\Order::whereDate('created_at', $today)
                ->where('status_order_id', '!=', 5)
                ->sum('total');
            $ecomPending = \App\Models\Tenant\Order::where('status_order_id', 1)->count();

            return [
                'kpis' => [
                    'sales_today'     => ['amount' => $salesToday, 'count' => (int)$snToday->cnt + (int)$docToday->cnt],
                    'sales_month'     => ['amount' => $salesMonth, 'count' => $cntMonth],
                    'sales_year'      => ['amount' => $salesYear,  'count' => (int)$snYear->cnt],
                    'avg_ticket'      => $avgTicket,
                    'utility_month'   => $utility,
                    'purchases_month' => ['amount' => round((float)$purchases->total, 2), 'count' => (int)$purchases->cnt],
                    'ecom_orders_today'  => $ecomOrdersToday,
                    'ecom_revenue_today' => round($ecomRevenueToday, 2),
                    'ecom_pending'       => $ecomPending,
                ],
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  GRÁFICO DIARIO — últimos 30 días (caché 15 min)
    // ─────────────────────────────────────────────────────────────────────
    public function salesDailyChart(): array
    {
        $cacheKey = "dash_v2_daily_{$this->establishmentId}";

        return Cache::remember($cacheKey, 900, function () {
            $start  = Carbon::now()->subDays(29)->toDateString();
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            $snData = $this->snQuery()
                ->whereIn('state_type_id', $states)
                ->where('date_of_issue', '>=', $start)
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('DATE(date_of_issue) as dt, SUM(total) as total, COUNT(*) as cnt')
                ->groupByRaw('DATE(date_of_issue)')
                ->get()->keyBy('dt');

            $docData = $this->docQuery()
                ->whereIn('state_type_id', $states)
                ->where('date_of_issue', '>=', $start)
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('DATE(date_of_issue) as dt, SUM(total) as total, COUNT(*) as cnt')
                ->groupByRaw('DATE(date_of_issue)')
                ->get()->keyBy('dt');

            $labels = [];
            $totals = [];
            $counts = [];

            for ($i = 29; $i >= 0; $i--) {
                $d        = Carbon::now()->subDays($i)->toDateString();
                $labels[] = Carbon::parse($d)->format('d M');
                $sn       = $snData[$d]  ?? null;
                $doc      = $docData[$d] ?? null;
                $totals[] = round(((float)($sn->total  ?? 0)) + ((float)($doc->total  ?? 0)), 2);
                $counts[] = ((int)($sn->cnt ?? 0)) + ((int)($doc->cnt ?? 0));
            }

            return compact('labels', 'totals', 'counts');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  GRÁFICO MENSUAL — últimos 12 meses (caché 30 min)
    // ─────────────────────────────────────────────────────────────────────
    public function salesMonthlyChart(): array
    {
        $cacheKey = "dash_v2_monthly_{$this->establishmentId}";

        return Cache::remember($cacheKey, 1800, function () {
            $start  = Carbon::now()->subMonths(11)->startOfMonth()->toDateString();
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            $snData = $this->snQuery()
                ->whereIn('state_type_id', $states)
                ->where('date_of_issue', '>=', $start)
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw("DATE_FORMAT(date_of_issue,'%Y-%m') as mo, SUM(total) as total")
                ->groupByRaw("DATE_FORMAT(date_of_issue,'%Y-%m')")
                ->get()->keyBy('mo');

            $docData = $this->docQuery()
                ->whereIn('state_type_id', $states)
                ->where('date_of_issue', '>=', $start)
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw("DATE_FORMAT(date_of_issue,'%Y-%m') as mo, SUM(total) as total")
                ->groupByRaw("DATE_FORMAT(date_of_issue,'%Y-%m')")
                ->get()->keyBy('mo');

            $labels = [];
            $totals = [];

            for ($i = 11; $i >= 0; $i--) {
                $date     = Carbon::now()->subMonths($i);
                $mo       = $date->format('Y-m');
                $labels[] = $date->format('M Y');
                $sn       = $snData[$mo]  ?? null;
                $doc      = $docData[$mo] ?? null;
                $totals[] = round(((float)($sn->total ?? 0)) + ((float)($doc->total ?? 0)), 2);
            }

            return compact('labels', 'totals');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  RANKING VENDEDORES (caché 10 min)
    // ─────────────────────────────────────────────────────────────────────
    public function topSellers(string $dateStart, string $dateEnd): array
    {
        if (empty($dateStart)) $dateStart = Carbon::now()->startOfMonth()->toDateString();
        if (empty($dateEnd))   $dateEnd   = Carbon::now()->endOfMonth()->toDateString();

        $cacheKey = "dash_v2_sellers_{$this->establishmentId}_{$dateStart}_{$dateEnd}";

        return Cache::remember($cacheKey, 600, function () use ($dateStart, $dateEnd) {
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            // seller_id = vendedor real; user_id = quien registró (puede ser admin)
            $rows = $this->tenantDb()
                ->table('sale_notes as sn')
                ->join('users as u', 'u.id', '=', $this->tenantDb()->raw('COALESCE(sn.seller_id, sn.user_id)'))
                ->whereIn('sn.state_type_id', $states)
                ->whereRaw('DATE(sn.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('sn.establishment_id', $estId))
                ->selectRaw('u.id, u.name, COUNT(sn.id) as cnt, SUM(sn.total) as total, AVG(sn.total) as avg_ticket')
                ->groupBy('u.id', 'u.name')
                ->orderByDesc('total')
                ->limit(10)
                ->get();

            return $rows->map(fn($r) => [
                'id'         => $r->id,
                'name'       => $r->name,
                'count'      => (int)$r->cnt,
                'total'      => round((float)$r->total, 2),
                'avg_ticket' => round((float)$r->avg_ticket, 2),
            ])->values()->toArray();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  TOP PRODUCTOS MÁS VENDIDOS (caché 10 min)
    // ─────────────────────────────────────────────────────────────────────
    public function topProducts(string $dateStart, string $dateEnd): array
    {
        if (empty($dateStart)) $dateStart = Carbon::now()->startOfMonth()->toDateString();
        if (empty($dateEnd))   $dateEnd   = Carbon::now()->endOfMonth()->toDateString();

        $cacheKey = "dash_v2_products_{$this->establishmentId}_{$dateStart}_{$dateEnd}";

        return Cache::remember($cacheKey, 600, function () use ($dateStart, $dateEnd) {
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            // ANY_VALUE() resuelve SQLSTATE[42000] con sql_mode=only_full_group_by
            $rows = $this->snItemQuery()
                ->join('sale_notes as sn', 'sn.id', '=', 'sale_note_items.sale_note_id')
                ->whereIn('sn.state_type_id', $states)
                ->whereRaw('DATE(sn.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('sn.establishment_id', $estId))
                ->selectRaw("
                    sale_note_items.item_id,
                    ANY_VALUE(JSON_UNQUOTE(JSON_EXTRACT(sale_note_items.item, '$.description'))) as name,
                    SUM(sale_note_items.quantity)   as qty,
                    SUM(sale_note_items.total)      as total,
                    AVG(sale_note_items.unit_price) as avg_price
                ")
                ->groupBy('sale_note_items.item_id')
                ->orderByDesc('qty')
                ->limit(10)
                ->get();

            return $rows->map(fn($r) => [
                'item_id'   => $r->item_id,
                'name'      => $r->name,
                'qty'       => round((float)$r->qty,       2),
                'total'     => round((float)$r->total,     2),
                'avg_price' => round((float)$r->avg_price, 2),
            ])->values()->toArray();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  STOCK CRÍTICO (caché 5 min)
    // ─────────────────────────────────────────────────────────────────────
    public function stockAlerts(): array
    {
        $cacheKey = "dash_v2_stock_{$this->establishmentId}";

        return Cache::remember($cacheKey, 300, function () {
            $estId = $this->establishmentId;

            // ItemWarehouse para usar la conexión del tenant
            $rows = ItemWarehouse::query()->toBase()
                ->join('items as i', 'i.id', '=', 'item_warehouse.item_id')
                ->join('warehouses as w', 'w.id', '=', 'item_warehouse.warehouse_id')
                ->where('i.active', 1)
                ->whereRaw('(item_warehouse.stock_physical - item_warehouse.stock_committed) <= i.stock_min')
                ->when($estId, fn($q) => $q->where('w.establishment_id', $estId))
                ->select([
                    'i.id as item_id',
                    'i.description as name',
                    'i.internal_id',
                    'i.stock_min',
                    'item_warehouse.stock_physical',
                    'item_warehouse.stock_committed',
                    \Illuminate\Support\Facades\DB::raw('(item_warehouse.stock_physical - item_warehouse.stock_committed) as stock_available'),
                    'w.description as warehouse',
                ])
                ->orderByRaw('(item_warehouse.stock_physical - item_warehouse.stock_committed) ASC')
                ->limit(20)
                ->get();

            return $rows->map(fn($r) => [
                'item_id'         => $r->item_id,
                'name'            => $r->name,
                'internal_id'     => $r->internal_id,
                'stock_available' => (float)$r->stock_available,
                'stock_min'       => (float)$r->stock_min,
                'stock_physical'  => (float)$r->stock_physical,
                'stock_committed' => (float)$r->stock_committed,
                'warehouse'       => $r->warehouse,
                'status'          => (float)$r->stock_available <= 0 ? 'out' : 'low',
            ])->values()->toArray();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  RESUMEN COMPRAS — mes actual (caché 10 min)
    // ─────────────────────────────────────────────────────────────────────
    public function purchaseSummary(): array
    {
        $cacheKey = "dash_v2_purchases_{$this->establishmentId}";

        return Cache::remember($cacheKey, 600, function () {
            $mStart = Carbon::now()->startOfMonth()->toDateString();
            $mEnd   = Carbon::now()->endOfMonth()->toDateString();
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            $summary = $this->purchaseQuery()
                ->whereIn('state_type_id', $states)
                ->whereBetween('date_of_issue', [$mStart, $mEnd])
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
                ->first();

            $recent = $this->tenantDb()
                ->table('purchases as p')
                ->join('persons as pe', 'pe.id', '=', 'p.supplier_id')
                ->whereIn('p.state_type_id', $states)
                ->when($estId, fn($q) => $q->where('p.establishment_id', $estId))
                ->select(['p.id', 'p.date_of_issue', 'p.total', 'pe.name as supplier'])
                ->orderByDesc('p.date_of_issue')
                ->limit(5)
                ->get();

            $topSuppliers = $this->tenantDb()
                ->table('purchases as p')
                ->join('persons as pe', 'pe.id', '=', 'p.supplier_id')
                ->whereIn('p.state_type_id', $states)
                ->whereBetween('p.date_of_issue', [$mStart, $mEnd])
                ->when($estId, fn($q) => $q->where('p.establishment_id', $estId))
                ->selectRaw('pe.id, pe.name, SUM(p.total) as total, COUNT(p.id) as cnt')
                ->groupBy('pe.id', 'pe.name')
                ->orderByDesc('total')
                ->limit(5)
                ->get();

            return [
                'month_total'   => round((float)$summary->total, 2),
                'month_count'   => (int)$summary->cnt,
                'recent'        => $recent,
                'top_suppliers' => $topSuppliers,
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  ALERTAS INTELIGENTES (caché 5 min)
    // ─────────────────────────────────────────────────────────────────────
    public function alerts(): array
    {
        $cacheKey = "dash_v2_alerts_{$this->establishmentId}";

        return Cache::remember($cacheKey, 300, function () {
            $alerts = [];
            $today  = Carbon::today()->toDateString();
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            // — Ventas esta semana vs semana anterior ────────────────
            $thisWeek = (float) $this->snQuery()
                ->whereIn('state_type_id', $states)
                ->whereBetween('date_of_issue', [
                    Carbon::now()->startOfWeek()->toDateString(), $today,
                ])
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->sum('total');

            $lastWeek = (float) $this->snQuery()
                ->whereIn('state_type_id', $states)
                ->whereBetween('date_of_issue', [
                    Carbon::now()->subWeek()->startOfWeek()->toDateString(),
                    Carbon::now()->subWeek()->endOfWeek()->toDateString(),
                ])
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->sum('total');

            if ($lastWeek > 0) {
                $pct = (($thisWeek - $lastWeek) / $lastWeek) * 100;
                if ($pct <= -15) {
                    $alerts[] = [
                        'type'    => 'warning',
                        'icon'    => 'trending-down',
                        'title'   => 'Ventas a la baja',
                        'message' => sprintf('Esta semana cayeron %.1f%% vs la semana anterior', abs($pct)),
                    ];
                } elseif ($pct >= 15) {
                    $alerts[] = [
                        'type'    => 'success',
                        'icon'    => 'trending-up',
                        'title'   => '¡Ventas al alza!',
                        'message' => sprintf('Esta semana crecieron %.1f%% vs la semana anterior', $pct),
                    ];
                }
            }

            // — Stock crítico ─────────────────────────────────────────
            $criticalCount = ItemWarehouse::query()->toBase()
                ->join('items as i', 'i.id', '=', 'item_warehouse.item_id')
                ->where('i.active', 1)
                ->whereRaw('(item_warehouse.stock_physical - item_warehouse.stock_committed) <= i.stock_min')
                ->count();

            if ($criticalCount > 0) {
                $alerts[] = [
                    'type'    => 'danger',
                    'icon'    => 'package',
                    'title'   => 'Stock crítico',
                    'message' => "{$criticalCount} " . ($criticalCount === 1 ? 'producto' : 'productos') . ' con stock crítico o agotado',
                ];
            }

            // — Sin ventas hoy (solo después de las 10 AM) ────────────
            if (now()->hour >= 10) {
                $todayCount = $this->snQuery()
                    ->whereIn('state_type_id', $states)
                    ->whereDate('date_of_issue', $today)
                    ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                    ->count();

                if ($todayCount === 0) {
                    $alerts[] = [
                        'type'    => 'warning',
                        'icon'    => 'alert-circle',
                        'title'   => 'Sin ventas hoy',
                        'message' => 'No se han registrado ventas hasta ahora',
                    ];
                }
            }

            return $alerts;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  CUENTAS POR COBRAR (caché 5 min)
    // ─────────────────────────────────────────────────────────────────────
    public function receivables(string $dateStart, string $dateEnd): array
    {
        if (empty($dateStart)) $dateStart = Carbon::now()->startOfMonth()->toDateString();
        if (empty($dateEnd))   $dateEnd   = Carbon::now()->endOfMonth()->toDateString();

        $cacheKey = "dash_v2_recv_{$this->establishmentId}_{$dateStart}_{$dateEnd}";

        return Cache::remember($cacheKey, 300, function () use ($dateStart, $dateEnd) {
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            // Documentos con saldo pendiente
            $docRows = $this->tenantDb()
                ->table('documents as d')
                ->join('persons as p', 'p.id', '=', 'd.customer_id')
                ->leftJoin('document_payments as dp', 'dp.document_id', '=', 'd.id')
                ->whereIn('d.state_type_id', $states)
                ->whereNotIn('d.document_type_id', ['08'])
                ->whereRaw('DATE(d.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('d.establishment_id', $estId))
                ->selectRaw('p.id as cid, ANY_VALUE(p.name) as cname,
                    SUM(d.total) as billed, COALESCE(SUM(dp.payment),0) as paid')
                ->groupBy('p.id')
                ->havingRaw('billed > paid + 0.01')
                ->get();

            // Notas de venta con saldo pendiente
            $snRows = $this->tenantDb()
                ->table('sale_notes as sn')
                ->join('persons as p', 'p.id', '=', 'sn.customer_id')
                ->leftJoin('sale_note_payments as snp', 'snp.sale_note_id', '=', 'sn.id')
                ->whereIn('sn.state_type_id', $states)
                ->where('sn.changed', false)
                ->whereRaw('DATE(sn.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('sn.establishment_id', $estId))
                ->selectRaw('p.id as cid, ANY_VALUE(p.name) as cname,
                    SUM(sn.total) as billed, COALESCE(SUM(snp.payment),0) as paid')
                ->groupBy('p.id')
                ->havingRaw('billed > paid + 0.01')
                ->get();

            // Combinar por cliente
            $map = [];
            foreach ([$docRows, $snRows] as $rows) {
                foreach ($rows as $r) {
                    $k = $r->cid;
                    if (!isset($map[$k])) {
                        $map[$k] = ['id' => $k, 'name' => $r->cname, 'billed' => 0, 'paid' => 0];
                    }
                    $map[$k]['billed'] += (float)$r->billed;
                    $map[$k]['paid']   += (float)$r->paid;
                }
            }

            $customers = collect($map)
                ->filter(fn($c) => ($c['billed'] - $c['paid']) > 0.01)
                ->sortByDesc(fn($c) => $c['billed'] - $c['paid'])
                ->take(10)
                ->values()
                ->map(fn($c) => [
                    'id'      => $c['id'],
                    'name'    => $c['name'],
                    'billed'  => round($c['billed'], 2),
                    'paid'    => round($c['paid'],   2),
                    'pending' => round($c['billed'] - $c['paid'], 2),
                ]);

            return [
                'total_pending' => round($customers->sum('pending'), 2),
                'total_billed'  => round($customers->sum('billed'),  2),
                'total_paid'    => round($customers->sum('paid'),     2),
                'customers'     => $customers->toArray(),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  ESTADÍSTICAS DE CLIENTES (caché 10 min)
    // ─────────────────────────────────────────────────────────────────────
    public function customers(string $dateStart, string $dateEnd): array
    {
        if (empty($dateStart)) $dateStart = Carbon::now()->startOfMonth()->toDateString();
        if (empty($dateEnd))   $dateEnd   = Carbon::now()->endOfMonth()->toDateString();

        $cacheKey = "dash_v2_cust_{$this->establishmentId}_{$dateStart}_{$dateEnd}";

        return Cache::remember($cacheKey, 600, function () use ($dateStart, $dateEnd) {
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            // Todos los clientes que compraron en el período (NV + Doc)
            $periodCustomers = $this->tenantDb()
                ->table('sale_notes as sn')
                ->join('persons as p', 'p.id', '=', 'sn.customer_id')
                ->whereIn('sn.state_type_id', $states)
                ->where('sn.changed', false)
                ->whereRaw('DATE(sn.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('sn.establishment_id', $estId))
                ->selectRaw('p.id, ANY_VALUE(p.name) as name, SUM(sn.total) as total, COUNT(sn.id) as cnt')
                ->groupBy('p.id')
                ->get();

            // Top clientes: los 10 con mayor total
            $top = $periodCustomers->sortByDesc('total')->take(10)->values()
                ->map(fn($r) => [
                    'id'    => $r->id,
                    'name'  => $r->name,
                    'total' => round((float)$r->total, 2),
                    'cnt'   => (int)$r->cnt,
                ]);

            // Clientes nuevos vs recurrentes
            $periodIds = $periodCustomers->pluck('id')->toArray();
            $newCount  = 0;
            if ($periodIds) {
                $newCount = $this->tenantDb()
                    ->table('sale_notes')
                    ->whereIn('customer_id', $periodIds)
                    ->where('changed', false)
                    ->whereRaw('DATE(date_of_issue) < ?', [$dateStart])
                    ->distinct('customer_id')
                    ->count('customer_id');
                // Los que NO tenían ventas antes = nuevos
                $returningCount = $newCount;
                $newCount       = count($periodIds) - $returningCount;
            } else {
                $returningCount = 0;
            }

            $totalSales = $periodCustomers->sum('total');
            $totalCnt   = $periodCustomers->sum('cnt');

            return [
                'period_count'    => count($periodIds),
                'new_count'       => max(0, $newCount),
                'returning_count' => max(0, $periodCustomers->count() - max(0, $newCount)),
                'avg_ticket'      => $totalCnt > 0 ? round($totalSales / $totalCnt, 2) : 0,
                'top'             => $top->toArray(),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  MÉTODOS DE PAGO (caché 10 min)
    // ─────────────────────────────────────────────────────────────────────
    public function paymentMethods(string $dateStart, string $dateEnd): array
    {
        if (empty($dateStart)) $dateStart = Carbon::now()->startOfMonth()->toDateString();
        if (empty($dateEnd))   $dateEnd   = Carbon::now()->endOfMonth()->toDateString();

        $cacheKey = "dash_v2_pay_{$this->establishmentId}_{$dateStart}_{$dateEnd}";

        return Cache::remember($cacheKey, 600, function () use ($dateStart, $dateEnd) {
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            // Pagos de NV
            $snPay = $this->tenantDb()
                ->table('sale_note_payments as snp')
                ->join('sale_notes as sn', 'sn.id', '=', 'snp.sale_note_id')
                ->join('cat_payment_method_types as pmt', 'pmt.id', '=', 'snp.payment_method_type_id')
                ->whereIn('sn.state_type_id', $states)
                ->where('sn.changed', false)
                ->whereRaw('DATE(sn.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('sn.establishment_id', $estId))
                ->selectRaw('snp.payment_method_type_id as id,
                    ANY_VALUE(pmt.description) as label,
                    SUM(snp.payment) as total, COUNT(*) as cnt')
                ->groupBy('snp.payment_method_type_id')
                ->get();

            // Pagos de CPE
            $docPay = $this->tenantDb()
                ->table('document_payments as dp')
                ->join('documents as d', 'd.id', '=', 'dp.document_id')
                ->join('cat_payment_method_types as pmt', 'pmt.id', '=', 'dp.payment_method_type_id')
                ->whereIn('d.state_type_id', $states)
                ->whereRaw('DATE(d.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('d.establishment_id', $estId))
                ->selectRaw('dp.payment_method_type_id as id,
                    ANY_VALUE(pmt.description) as label,
                    SUM(dp.payment) as total, COUNT(*) as cnt')
                ->groupBy('dp.payment_method_type_id')
                ->get();

            // Combinar
            $map = [];
            foreach ([$snPay, $docPay] as $rows) {
                foreach ($rows as $r) {
                    $k = $r->id;
                    if (!isset($map[$k])) {
                        $map[$k] = ['id' => $k, 'label' => $r->label, 'total' => 0, 'cnt' => 0];
                    }
                    $map[$k]['total'] += (float)$r->total;
                    $map[$k]['cnt']   += (int)$r->cnt;
                }
            }

            return collect($map)->sortByDesc('total')->values()
                ->map(fn($m) => [
                    'id'    => $m['id'],
                    'label' => $m['label'],
                    'total' => round($m['total'], 2),
                    'cnt'   => $m['cnt'],
                ])->toArray();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  RENTABILIDAD POR PRODUCTO (caché 15 min)
    // ─────────────────────────────────────────────────────────────────────
    public function profitability(string $dateStart, string $dateEnd): array
    {
        if (empty($dateStart)) $dateStart = Carbon::now()->startOfMonth()->toDateString();
        if (empty($dateEnd))   $dateEnd   = Carbon::now()->endOfMonth()->toDateString();

        $cacheKey = "dash_v2_profit_{$this->establishmentId}_{$dateStart}_{$dateEnd}";

        return Cache::remember($cacheKey, 900, function () use ($dateStart, $dateEnd) {
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            $rows = $this->snItemQuery()
                ->join('sale_notes as sn', 'sn.id', '=', 'sale_note_items.sale_note_id')
                ->whereIn('sn.state_type_id', $states)
                ->whereRaw('DATE(sn.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('sn.establishment_id', $estId))
                ->selectRaw("
                    sale_note_items.item_id,
                    ANY_VALUE(JSON_UNQUOTE(JSON_EXTRACT(sale_note_items.item,'$.description'))) as name,
                    SUM(sale_note_items.quantity)   as qty,
                    SUM(sale_note_items.total)       as revenue,
                    SUM(sale_note_items.quantity *
                        COALESCE(
                            CAST(JSON_UNQUOTE(JSON_EXTRACT(sale_note_items.item,'$.purchase_unit_price')) AS DECIMAL(15,4)),
                        0))                          as cost
                ")
                ->groupBy('sale_note_items.item_id')
                ->havingRaw('revenue > 0')
                ->orderByRaw('(revenue - cost) DESC')
                ->limit(10)
                ->get();

            return $rows->map(fn($r) => [
                'item_id' => $r->item_id,
                'name'    => $r->name,
                'revenue' => round((float)$r->revenue, 2),
                'cost'    => round((float)$r->cost,    2),
                'profit'  => round((float)$r->revenue - (float)$r->cost, 2),
                'margin'  => (float)$r->revenue > 0
                    ? round((((float)$r->revenue - (float)$r->cost) / (float)$r->revenue) * 100, 1)
                    : 0,
            ])->values()->toArray();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  COMPARATIVO DE PERÍODOS (caché 30 min)
    // ─────────────────────────────────────────────────────────────────────
    public function periodComparison(): array
    {
        $cacheKey = "dash_v2_period_{$this->establishmentId}";

        return Cache::remember($cacheKey, 1800, function () {
            $states = $this->validStates;
            $estId  = $this->establishmentId;
            $now    = Carbon::now();

            $periods = [
                'curr_month' => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
                'prev_month' => [$now->copy()->subMonth()->startOfMonth()->toDateString(), $now->copy()->subMonth()->endOfMonth()->toDateString()],
                'curr_year'  => [$now->copy()->startOfYear()->toDateString(),  $now->copy()->endOfYear()->toDateString()],
                'prev_year'  => [$now->copy()->subYear()->startOfYear()->toDateString(),   $now->copy()->subYear()->endOfYear()->toDateString()],
            ];

            $result = [];
            foreach ($periods as $key => [$start, $end]) {
                $sn  = $this->snQuery()
                    ->whereIn('state_type_id', $states)
                    ->whereBetween('date_of_issue', [$start, $end])
                    ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                    ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
                    ->first();
                $doc = $this->docQuery()
                    ->whereIn('state_type_id', $states)
                    ->whereBetween('date_of_issue', [$start, $end])
                    ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                    ->selectRaw('COALESCE(SUM(total),0) as total, COUNT(*) as cnt')
                    ->first();

                $total = round((float)$sn->total + (float)$doc->total, 2);
                $cnt   = (int)$sn->cnt + (int)$doc->cnt;

                $result[$key] = [
                    'total'      => $total,
                    'count'      => $cnt,
                    'avg_ticket' => $cnt > 0 ? round($total / $cnt, 2) : 0,
                    'label'      => match($key) {
                        'curr_month' => Carbon::parse($start)->translatedFormat('F Y'),
                        'prev_month' => Carbon::parse($start)->translatedFormat('F Y'),
                        'curr_year'  => $now->year,
                        'prev_year'  => $now->year - 1,
                    },
                ];
            }

            // Variaciones porcentuales
            $calcPct = fn($curr, $prev) => $prev > 0 ? round((($curr - $prev) / $prev) * 100, 1) : null;

            return [
                'months' => [
                    'current'    => $result['curr_month'],
                    'previous'   => $result['prev_month'],
                    'change_pct' => $calcPct($result['curr_month']['total'], $result['prev_month']['total']),
                ],
                'years' => [
                    'current'    => $result['curr_year'],
                    'previous'   => $result['prev_year'],
                    'change_pct' => $calcPct($result['curr_year']['total'], $result['prev_year']['total']),
                ],
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  INVENTARIO AVANZADO (caché 10 min)
    // ─────────────────────────────────────────────────────────────────────
    public function inventoryAdvanced(): array
    {
        $cacheKey = "dash_v2_inv_{$this->establishmentId}";

        return Cache::remember($cacheKey, 600, function () {
            $estId = $this->establishmentId;

            // Valor total del stock
            // Nota: la columna `sale_unit_price_default` (JSON) NO existe en
            // items — solo `sale_unit_price` (decimal). El intento de leerla
            // con JSON_EXTRACT generaba "Unknown column" → 500 en el endpoint.
            $valueRow = ItemWarehouse::query()->toBase()
                ->join('items as i', 'i.id', '=', 'item_warehouse.item_id')
                ->where('i.active', 1)
                ->when($estId, fn($q) => $q
                    ->join('warehouses as w', 'w.id', '=', 'item_warehouse.warehouse_id')
                    ->where('w.establishment_id', $estId))
                ->selectRaw("
                    SUM(item_warehouse.stock_physical *
                        COALESCE(i.sale_unit_price, 0)
                    ) as value,
                    SUM(item_warehouse.stock_physical) as total_units,
                    COUNT(DISTINCT i.id) as product_count
                ")
                ->first();

            // Productos sin movimiento en últimos 30 días
            $cutoff = Carbon::now()->subDays(30)->toDateString();
            $activeItemIds = $this->snItemQuery()
                ->join('sale_notes as sn', 'sn.id', '=', 'sale_note_items.sale_note_id')
                ->where('sn.date_of_issue', '>=', $cutoff)
                ->pluck('sale_note_items.item_id')
                ->unique();

            $noMovement = ItemWarehouse::query()->toBase()
                ->join('items as i', 'i.id', '=', 'item_warehouse.item_id')
                ->where('i.active', 1)
                ->where('item_warehouse.stock_physical', '>', 0)
                ->whereNotIn('i.id', $activeItemIds->toArray())
                ->count();

            // Top productos por valor de inventario
            $topValue = ItemWarehouse::query()->toBase()
                ->join('items as i', 'i.id', '=', 'item_warehouse.item_id')
                ->where('i.active', 1)
                ->where('item_warehouse.stock_physical', '>', 0)
                ->selectRaw("
                    i.id, i.description as name, i.internal_id,
                    item_warehouse.stock_physical as stock,
                    COALESCE(i.sale_unit_price, 0) as price,
                    (item_warehouse.stock_physical * COALESCE(i.sale_unit_price, 0)) as value
                ")
                ->orderByRaw('value DESC')
                ->limit(8)
                ->get();

            return [
                'total_value'     => round((float)($valueRow->value ?? 0), 2),
                'total_units'     => (float)($valueRow->total_units    ?? 0),
                'product_count'   => (int)($valueRow->product_count    ?? 0),
                'no_movement_30d' => (int)$noMovement,
                'top_by_value'    => $topValue->map(fn($r) => [
                    'id'    => $r->id,
                    'name'  => $r->name,
                    'code'  => $r->internal_id,
                    'stock' => (float)$r->stock,
                    'price' => round((float)$r->price, 2),
                    'value' => round((float)$r->value, 2),
                ])->values()->toArray(),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  VENTAS POR HORA DEL DÍA (caché 15 min)
    // ─────────────────────────────────────────────────────────────────────
    public function salesByHour(string $dateStart, string $dateEnd): array
    {
        if (empty($dateStart)) $dateStart = Carbon::now()->startOfMonth()->toDateString();
        if (empty($dateEnd))   $dateEnd   = Carbon::now()->endOfMonth()->toDateString();

        $cacheKey = "dash_v2_hour_{$this->establishmentId}_{$dateStart}_{$dateEnd}";

        return Cache::remember($cacheKey, 900, function () use ($dateStart, $dateEnd) {
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            $rows = $this->snQuery()
                ->whereIn('state_type_id', $states)
                ->where('changed', false)
                ->whereRaw('DATE(date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('HOUR(created_at) as hr, SUM(total) as total, COUNT(*) as cnt')
                ->groupByRaw('HOUR(created_at)')
                ->orderBy('hr')
                ->get()
                ->keyBy('hr');

            $labels = [];
            $totals = [];
            $counts = [];

            for ($h = 6; $h <= 22; $h++) {
                $labels[] = sprintf('%02d:00', $h);
                $row      = $rows[$h] ?? null;
                $totals[] = round((float)($row->total ?? 0), 2);
                $counts[] = (int)($row->cnt ?? 0);
            }

            return compact('labels', 'totals', 'counts');
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  CONVERSIÓN COTIZACIONES → VENTAS (caché 15 min)
    // ─────────────────────────────────────────────────────────────────────
    public function quotationConversion(string $dateStart, string $dateEnd): array
    {
        if (empty($dateStart)) $dateStart = Carbon::now()->startOfMonth()->toDateString();
        if (empty($dateEnd))   $dateEnd   = Carbon::now()->endOfMonth()->toDateString();

        $cacheKey = "dash_v2_quot_{$this->establishmentId}_{$dateStart}_{$dateEnd}";

        return Cache::remember($cacheKey, 900, function () use ($dateStart, $dateEnd) {
            $estId = $this->establishmentId;

            // Total de cotizaciones en el período
            $totalRow = $this->tenantDb()
                ->table('quotations')
                ->whereRaw('DATE(date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q) => $q->where('establishment_id', $estId))
                ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total),0) as total')
                ->first();

            // Cotizaciones convertidas a documentos en el período
            $convertedDoc = $this->tenantDb()
                ->table('documents as d')
                ->join('quotations as q', 'q.id', '=', 'd.quotation_id')
                ->whereNotNull('d.quotation_id')
                ->whereRaw('DATE(q.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q2) => $q2->where('q.establishment_id', $estId))
                ->selectRaw('COUNT(DISTINCT d.quotation_id) as cnt, COALESCE(SUM(d.total),0) as total')
                ->first();

            // Cotizaciones convertidas a notas de venta en el período
            $convertedSN = $this->tenantDb()
                ->table('sale_notes as sn')
                ->join('quotations as q', 'q.id', '=', 'sn.quotation_id')
                ->whereNotNull('sn.quotation_id')
                ->whereRaw('DATE(q.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->when($estId, fn($q2) => $q2->where('q.establishment_id', $estId))
                ->selectRaw('COUNT(DISTINCT sn.quotation_id) as cnt, COALESCE(SUM(sn.total),0) as total')
                ->first();

            $totalQuot     = (int)$totalRow->cnt;
            $totalAmount   = (float)$totalRow->total;
            $convertedCnt  = (int)$convertedDoc->cnt + (int)$convertedSN->cnt;
            $convertedAmt  = (float)$convertedDoc->total + (float)$convertedSN->total;
            $rate          = $totalQuot > 0 ? round(($convertedCnt / $totalQuot) * 100, 1) : 0;
            $lostAmount    = round(max(0, $totalAmount - $convertedAmt), 2);

            return [
                'total_quotations' => $totalQuot,
                'total_amount'     => round($totalAmount,   2),
                'converted_count'  => $convertedCnt,
                'converted_amount' => round($convertedAmt, 2),
                'lost_amount'      => $lostAmount,
                'conversion_rate'  => $rate,
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    //  VENTAS POR CIUDAD / DEPARTAMENTO (caché 15 min)
    // ─────────────────────────────────────────────────────────────────────
    public function salesByCity(string $dateStart, string $dateEnd): array
    {
        if (empty($dateStart)) $dateStart = Carbon::now()->startOfMonth()->toDateString();
        if (empty($dateEnd))   $dateEnd   = Carbon::now()->endOfMonth()->toDateString();

        $cacheKey = "dash_v2_city_{$this->establishmentId}_{$dateStart}_{$dateEnd}";

        return Cache::remember($cacheKey, 900, function () use ($dateStart, $dateEnd) {
            $states = $this->validStates;
            $estId  = $this->establishmentId;

            $rows = $this->tenantDb()
                ->table('sale_notes as sn')
                ->join('persons as p',      'p.id',  '=', 'sn.customer_id')
                ->join('departments as dep', 'dep.id', '=', 'p.department_id')
                ->whereIn('sn.state_type_id', $states)
                ->where('sn.changed', false)
                ->whereRaw('DATE(sn.date_of_issue) BETWEEN ? AND ?', [$dateStart, $dateEnd])
                ->whereNotNull('p.department_id')
                ->when($estId, fn($q) => $q->where('sn.establishment_id', $estId))
                ->selectRaw('
                    p.department_id,
                    ANY_VALUE(dep.description) as city,
                    SUM(sn.total) as total,
                    COUNT(sn.id)  as cnt,
                    COUNT(DISTINCT p.id) as customers
                ')
                ->groupBy('p.department_id')
                ->orderByDesc('total')
                ->limit(15)
                ->get();

            return $rows->map(fn($r) => [
                'department_id' => $r->department_id,
                'city'          => $r->city,
                'total'         => round((float)$r->total, 2),
                'count'         => (int)$r->cnt,
                'customers'     => (int)$r->customers,
            ])->values()->toArray();
        });
    }
}
