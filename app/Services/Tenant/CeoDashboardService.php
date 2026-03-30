<?php
namespace App\Services\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant\Order;
use App\Models\Tenant\Person;
use App\Models\Tenant\SaleNote;
use Carbon\Carbon;

class CeoDashboardService
{
    public function getStrategicKpis(?int $establishmentId = null): array
    {
        $cacheKey = "ceo_kpis_" . ($establishmentId ?? 'all');

        return Cache::remember($cacheKey, 600, function () use ($establishmentId) {
            $now = Carbon::now();
            $monthStart = $now->copy()->startOfMonth();
            $prevMonthStart = $now->copy()->subMonth()->startOfMonth();
            $prevMonthEnd = $now->copy()->subMonth()->endOfMonth();

            // Revenue this month vs last month
            $revenueMonth = $this->revenue($monthStart, $now, $establishmentId);
            $revenuePrev = $this->revenue($prevMonthStart, $prevMonthEnd, $establishmentId);
            $revenueGrowth = $revenuePrev > 0 ? round((($revenueMonth - $revenuePrev) / $revenuePrev) * 100, 1) : 0;

            // Profit margin
            $costMonth = $this->cost($monthStart, $now, $establishmentId);
            $profitMonth = $revenueMonth - $costMonth;
            $profitMargin = $revenueMonth > 0 ? round(($profitMonth / $revenueMonth) * 100, 1) : 0;

            // Customer metrics
            $newCustomers = Person::where('type', 'customers')
                ->whereBetween('created_at', [$monthStart, $now])->count();
            $repeatCustomers = $this->repeatCustomerRate($monthStart, $now);

            // Orders
            $ordersMonth = Order::whereBetween('created_at', [$monthStart, $now])
                ->where('status_order_id', '!=', 5)->count();
            $avgTicket = $ordersMonth > 0 ? round($revenueMonth / $ordersMonth, 2) : 0;

            // Accounts receivable
            $receivable = SaleNote::where('state_type_id', '01')
                ->whereHas('payments', function($q) {}, '<', DB::raw('total'))
                ->sum('total') ?? 0;

            // Cash flow (simplified)
            $cashIn = $revenueMonth;
            $cashOut = $costMonth;

            // Top risks
            $risks = $this->identifyRisks($establishmentId);

            return [
                'revenue' => [
                    'current' => round($revenueMonth, 2),
                    'previous' => round($revenuePrev, 2),
                    'growth_pct' => $revenueGrowth,
                    'trend' => $revenueGrowth >= 0 ? 'up' : 'down',
                ],
                'profit' => [
                    'amount' => round($profitMonth, 2),
                    'margin_pct' => $profitMargin,
                ],
                'customers' => [
                    'new_this_month' => $newCustomers,
                    'repeat_rate' => $repeatCustomers,
                ],
                'orders' => [
                    'count' => $ordersMonth,
                    'avg_ticket' => $avgTicket,
                ],
                'cash_flow' => [
                    'in' => round($cashIn, 2),
                    'out' => round($cashOut, 2),
                    'net' => round($cashIn - $cashOut, 2),
                ],
                'receivable' => round($receivable, 2),
                'risks' => $risks,
            ];
        });
    }

    public function getCustomerCohorts(): array
    {
        return Cache::remember('ceo_cohorts', 3600, function () {
            $months = collect();
            for ($i = 5; $i >= 0; $i--) {
                $start = now()->subMonths($i)->startOfMonth();
                $end = now()->subMonths($i)->endOfMonth();

                $newCustomers = Person::where('type', 'customers')
                    ->whereBetween('created_at', [$start, $end])->count();

                $returningInNext = 0;
                if ($i > 0) {
                    $nextStart = now()->subMonths($i - 1)->startOfMonth();
                    $nextEnd = now()->subMonths($i - 1)->endOfMonth();
                    // Customers who bought in both periods
                    $returningInNext = DB::connection('tenant')->table('sale_notes as sn1')
                        ->join('sale_notes as sn2', 'sn1.customer_id', '=', 'sn2.customer_id')
                        ->whereBetween('sn1.date_of_issue', [$start->format('Y-m-d'), $end->format('Y-m-d')])
                        ->whereBetween('sn2.date_of_issue', [$nextStart->format('Y-m-d'), $nextEnd->format('Y-m-d')])
                        ->where('sn1.state_type_id', '01')
                        ->where('sn2.state_type_id', '01')
                        ->distinct('sn1.customer_id')
                        ->count('sn1.customer_id');
                }

                $months->push([
                    'month' => $start->format('Y-m'),
                    'new_customers' => $newCustomers,
                    'retained_next_month' => $returningInNext,
                    'retention_rate' => $newCustomers > 0 ? round(($returningInNext / $newCustomers) * 100, 1) : 0,
                ]);
            }
            return $months->toArray();
        });
    }

    protected function revenue(Carbon $from, Carbon $to, ?int $estId): float
    {
        $q = SaleNote::where('state_type_id', '01')
            ->whereBetween('date_of_issue', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        if ($estId) $q->where('establishment_id', $estId);
        return (float) $q->sum('total');
    }

    protected function cost(Carbon $from, Carbon $to, ?int $estId): float
    {
        // Simplified: use purchase totals as cost proxy
        $q = DB::connection('tenant')->table('purchases')->where('state_type_id', '01')
            ->whereBetween('date_of_issue', [$from->format('Y-m-d'), $to->format('Y-m-d')]);
        return (float) $q->sum('total');
    }

    protected function repeatCustomerRate(Carbon $from, Carbon $to): float
    {
        $totalCustomers = SaleNote::where('state_type_id', '01')
            ->whereBetween('date_of_issue', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->distinct('customer_id')->count('customer_id');

        $repeatCustomers = DB::connection('tenant')->table('sale_notes')
            ->select('customer_id')
            ->where('state_type_id', '01')
            ->whereBetween('date_of_issue', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        return $totalCustomers > 0 ? round(($repeatCustomers / $totalCustomers) * 100, 1) : 0;
    }

    protected function identifyRisks(?int $estId): array
    {
        $risks = [];

        // Low stock items
        $lowStock = DB::connection('tenant')->table('item_warehouse')
            ->join('items', 'items.id', '=', 'item_warehouse.item_id')
            ->where('items.stock_min', '>', 0)
            ->whereColumn('item_warehouse.stock', '<=', 'items.stock_min')
            ->count();
        if ($lowStock > 0) {
            $risks[] = ['type' => 'warning', 'message' => "{$lowStock} productos con stock bajo", 'severity' => 'high'];
        }

        // Pending orders > 48h
        $staleOrders = Order::where('status_order_id', 1)
            ->where('created_at', '<', now()->subHours(48))->count();
        if ($staleOrders > 0) {
            $risks[] = ['type' => 'danger', 'message' => "{$staleOrders} pedidos sin verificar (+48h)", 'severity' => 'critical'];
        }

        // Overdue receivables
        $overdue = SaleNote::where('state_type_id', '01')
            ->where('date_of_issue', '<', now()->subDays(30))
            ->whereRaw('(SELECT COALESCE(SUM(payment), 0) FROM sale_note_payments WHERE sale_note_id = sale_notes.id) < sale_notes.total')
            ->count();
        if ($overdue > 0) {
            $risks[] = ['type' => 'warning', 'message' => "{$overdue} cuentas por cobrar vencidas (+30 días)", 'severity' => 'medium'];
        }

        return $risks;
    }
}
