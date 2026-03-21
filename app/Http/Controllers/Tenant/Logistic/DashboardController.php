<?php

namespace App\Http\Controllers\Tenant\Logistic;

use App\Http\Controllers\Controller;
use App\Enums\LogisticStatusEnum;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\LogisticReturn;
use App\Models\Tenant\LogisticReturnItem;
use App\Models\Tenant\ItemWarehouse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today     = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();

        // ── Despachos ─────────────────────────────────────────────────────────
        $dispatchStats = SaleNote::select('logistic_status', DB::raw('count(*) as total'))
            ->whereNotNull('logistic_status')
            ->groupBy('logistic_status')
            ->pluck('total', 'logistic_status');

        $despachados_hoy = SaleNote::where('logistic_status', LogisticStatusEnum::DESPACHADO->value)
            ->whereDate('updated_at', $today)
            ->count();

        $despachados_mes = SaleNote::where('logistic_status', LogisticStatusEnum::DESPACHADO->value)
            ->where('updated_at', '>=', $thisMonth)
            ->count();

        $en_cola = SaleNote::whereIn('logistic_status', [
            LogisticStatusEnum::PENDIENTE->value,
            LogisticStatusEnum::PREPARANDO->value,
            LogisticStatusEnum::LISTO_DESPACHO->value,
        ])->count();

        // ── Devoluciones ──────────────────────────────────────────────────────
        $returnStats = [
            'pendiente' => LogisticReturn::where('status', 'PENDIENTE')->count(),
            'recibido'  => LogisticReturn::where('status', 'RECIBIDO')->count(),
            'procesado' => LogisticReturn::where('status', 'PROCESADO')->count(),
            'mes'       => LogisticReturn::where('created_at', '>=', $thisMonth)->count(),
        ];

        // ── Stock crítico (disponible = físico - comprometido <= 0) ─────────
        $stockCritico = ItemWarehouse::with(['item', 'warehouse'])
            ->whereRaw('(stock_physical - stock_committed) <= 0')
            ->whereHas('item', fn($q) => $q->where('active', 1))
            ->orderBy('stock_physical')
            ->limit(15)
            ->get();

        $stockBajo = ItemWarehouse::with(['item', 'warehouse'])
            ->whereRaw('(stock_physical - stock_committed) > 0')
            ->whereRaw('(stock_physical - stock_committed) <= 5')
            ->whereHas('item', fn($q) => $q->where('active', 1))
            ->orderByRaw('(stock_physical - stock_committed)')
            ->limit(15)
            ->get();

        // ── Movimientos recientes de stock ────────────────────────────────────
        $movimientos = StockMovement::with(['item', 'reference'])
            ->orderByDesc('created_at')
            ->limit(12)
            ->get();

        // ── Despachos por día (últimos 14 días) ───────────────────────────────
        $despachosDiario = SaleNote::select(
                DB::raw('DATE(updated_at) as dia'),
                DB::raw('count(*) as total')
            )
            ->where('logistic_status', LogisticStatusEnum::DESPACHADO->value)
            ->where('updated_at', '>=', Carbon::now()->subDays(13))
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        $diasLabels  = [];
        $diasData    = [];
        for ($i = 13; $i >= 0; $i--) {
            $fecha       = Carbon::now()->subDays($i)->format('Y-m-d');
            $diasLabels[] = Carbon::now()->subDays($i)->format('d/m');
            $diasData[]   = $despachosDiario[$fecha]->total ?? 0;
        }

        // ── Top productos más devueltos ────────────────────────────────────────
        $topDevueltos = LogisticReturnItem::join('items as i', 'i.id', '=', 'logistic_return_items.item_id')
            ->select('i.description', DB::raw('SUM(logistic_return_items.quantity_returned) as total_devuelto'))
            ->groupBy('i.id', 'i.description')
            ->orderByDesc('total_devuelto')
            ->limit(5)
            ->get();

        return view('tenant.logistic.dashboard', compact(
            'dispatchStats',
            'despachados_hoy',
            'despachados_mes',
            'en_cola',
            'returnStats',
            'stockCritico',
            'stockBajo',
            'movimientos',
            'diasLabels',
            'diasData',
            'topDevueltos'
        ));
    }
}
