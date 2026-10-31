<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ShippingRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuánto se vende por día, por origen: tienda online, marketplace y registro
 * de pedidos (el enlace que llena el cliente).
 *
 * Mide lo COBRADO, no el total del pedido: los pedidos del enlace de registro
 * nacen sin productos (total 0) y su dinero vive en el envío y sus pagos. Se
 * suman los dos libros de pago (`order_payments` y `shipping_payments`), como
 * la columna Cobro (OrderController::sqlCobrado), sin los pedidos anulados.
 *
 * Lo creado desde el panel (alta manual, `seller_id` puesto) no entra en el
 * total: se informa aparte.
 */
class OrderSalesController extends Controller
{
    private const ORIGENES = ['tienda', 'marketplace', 'registro'];

    public function summary(Request $request)
    {
        $hasta = $this->fecha($request->input('hasta'), now())->endOfDay();
        $desde = $this->fecha($request->input('desde'), $hasta->copy())->startOfDay();
        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }
        if ($desde->diffInDays($hasta) > 370) {
            $desde = $hasta->copy()->subDays(370)->startOfDay();
        }

        $db = DB::connection('tenant');
        $origen = "CASE WHEN o.seller_id IS NOT NULL THEN 'panel'"
            . " WHEN c.code = 'ECOM' THEN 'tienda'"
            . " WHEN c.code LIKE 'MKP%' OR c.type = 'marketplace' THEN 'marketplace'"
            . " ELSE 'registro' END";

        $filas = collect();

        // Libro del pedido (tienda, marketplace y lo cobrado desde Pedidos).
        if (Schema::connection('tenant')->hasTable('order_payments')) {
            $filas = $filas->concat($db->table('order_payments as p')
                ->join('orders as o', 'o.id', '=', 'p.order_id')
                ->leftJoin('sales_channels as c', 'c.id', '=', 'o.channel_id')
                ->whereNull('o.deleted_at')->where('o.status_order_id', '!=', 5)
                ->whereBetween(DB::raw('COALESCE(p.date_of_payment, p.created_at)'), [$desde, $hasta])
                ->selectRaw("DATE(COALESCE(p.date_of_payment, p.created_at)) d, {$origen} origen, SUM(p.payment) monto, COUNT(DISTINCT o.id) pedidos")
                ->groupBy('d', 'origen')->get());
        }

        // Libro del envío (lo cobrado desde Envíos y el enlace de registro).
        if (ShippingRequest::moduleInstalled() && Schema::connection('tenant')->hasTable('shipping_payments')) {
            $filas = $filas->concat($db->table('shipping_payments as p')
                ->join('shipping_requests as s', 's.id', '=', 'p.shipment_id')
                ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
                ->leftJoin('sales_channels as c', 'c.id', '=', 'o.channel_id')
                ->whereNull('s.cancelled_at')
                ->where(fn ($q) => $q->whereNull('o.id')->orWhere(fn ($w) => $w->whereNull('o.deleted_at')->where('o.status_order_id', '!=', 5)))
                ->whereBetween(DB::raw('COALESCE(p.paid_at, p.created_at)'), [$desde, $hasta])
                ->selectRaw("DATE(COALESCE(p.paid_at, p.created_at)) d, {$origen} origen, SUM(p.amount) monto, COUNT(DISTINCT s.id) pedidos")
                ->groupBy('d', 'origen')->get());
        }

        $porOrigen = array_fill_keys(array_merge(self::ORIGENES, ['panel']), ['cobrado' => 0.0, 'pagos' => 0]);
        $porDia = [];
        foreach ($filas as $f) {
            $o = $f->origen;
            $porOrigen[$o]['cobrado'] += (float) $f->monto;
            $porOrigen[$o]['pagos'] += (int) $f->pedidos;
            if (in_array($o, self::ORIGENES, true)) {
                $porDia[$f->d] = ($porDia[$f->d] ?? array_fill_keys(self::ORIGENES, 0.0));
                $porDia[$f->d][$o] += (float) $f->monto;
            }
        }
        krsort($porDia);

        // Pedidos nuevos del periodo por origen (para «N pedidos»).
        $nuevos = $db->table('orders as o')->leftJoin('sales_channels as c', 'c.id', '=', 'o.channel_id')
            ->whereNull('o.deleted_at')->where('o.status_order_id', '!=', 5)
            ->whereBetween('o.created_at', [$desde, $hasta])
            ->selectRaw("{$origen} origen, COUNT(*) n")->groupBy('origen')->pluck('n', 'origen');

        $origenes = [];
        foreach (array_merge(self::ORIGENES, ['panel']) as $o) {
            $origenes[$o] = [
                'cobrado' => round($porOrigen[$o]['cobrado'], 2),
                'pedidos' => (int) ($nuevos[$o] ?? 0),
            ];
        }

        return response()->json([
            'desde'    => $desde->toDateString(),
            'hasta'    => $hasta->toDateString(),
            'total'    => round(array_sum(array_map(fn ($o) => $origenes[$o]['cobrado'], self::ORIGENES)), 2),
            'pedidos'  => array_sum(array_map(fn ($o) => $origenes[$o]['pedidos'], self::ORIGENES)),
            'origenes' => $origenes,
            'dias'     => collect($porDia)->map(fn ($v, $d) => [
                'fecha' => $d,
                'total' => round(array_sum($v), 2),
            ] + array_map(fn ($x) => round($x, 2), $v))->values(),
        ]);
    }

    private function fecha($valor, Carbon $defecto): Carbon
    {
        try {
            return $valor ? Carbon::parse($valor) : $defecto;
        } catch (\Throwable $e) {
            return $defecto;
        }
    }
}
