<?php

namespace App\Jobs\Marketplace;

use App\Services\Marketplace\MarketplaceCouponService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Libera cupones de plataforma redeemed cuyo pedido NUNCA llego a
 * payment_status=paid en N horas. Cubre el caso de webhook MP que
 * no llega: el cupon quedaria marcado used para siempre.
 *
 * Umbral: 24h. Se asume que en ese tiempo MP ya tomo decision (paid,
 * rejected o cancelled). Si el pedido pasa a paid despues, el cupon
 * ya fue liberado pero la orden quedo con el descuento aplicado —
 * eso es consistente (el comprador igual lo aprovecho).
 *
 * Schedule: diario 04:15 (despues de los recalc nocturnos).
 */
class ReleaseStaleCouponRedemptions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries   = 1;

    const STALE_HOURS = 24;

    public function handle(MarketplaceCouponService $svc): void
    {
        $cutoff = now()->subHours(self::STALE_HOURS);

        // Sub-orders con cupon platform usado y pedido viejo no-paid.
        $rows = DB::connection('system')->table('tenant_marketplace_orders as sub')
            ->join('marketplace_orders as mo', 'mo.id', '=', 'sub.marketplace_order_id')
            ->whereNotNull('sub.platform_coupon_assignment_id')
            ->where('mo.created_at', '<', $cutoff)
            ->where('mo.payment_status', '!=', 'paid')
            ->select('sub.platform_coupon_assignment_id as asg_id', 'mo.order_number')
            ->get();

        if ($rows->isEmpty()) return;

        $released = 0;
        foreach ($rows as $r) {
            try {
                if ($svc->releaseRedemption((int) $r->asg_id)) {
                    $released++;
                }
            } catch (\Throwable $e) {
                Log::warning('ReleaseStaleCouponRedemptions: fail per row', [
                    'asg_id' => $r->asg_id,
                    'order'  => $r->order_number,
                    'err'    => $e->getMessage(),
                ]);
            }
        }

        if ($released > 0) {
            Log::info('ReleaseStaleCouponRedemptions: cupones liberados', [
                'count'        => $released,
                'cutoff_hours' => self::STALE_HOURS,
            ]);
        }
    }
}
