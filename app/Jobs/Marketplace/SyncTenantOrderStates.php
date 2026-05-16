<?php

namespace App\Jobs\Marketplace;

use App\Models\System\MarketplaceUserOrder;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza el estado de los pedidos del tenant hacia el snapshot del
 * comprador (marketplace_user_orders), para que "Mis pedidos" refleje
 * cambios como "Despachado" o "Cancelado" sin instrumentar el flujo
 * legacy del tenant.
 *
 * Como funciona:
 *   1. Lista tenant_marketplace_orders con tenant_order_id NOT NULL
 *      y MarketplaceOrder.marketplace_user_id NOT NULL.
 *   2. Por tenant, switch context y lee orders.status_order_id.
 *   3. Mapea al snapshot (confirmed | completed | cancelled).
 *   4. Si difiere del estado conocido en marketplace_user_orders,
 *      dispatch PushOrderToSystem con el nuevo status.
 *
 * Schedule: cada hora. Eficiente: una query agrupada por tenant + un
 * SWITCH context por tenant (no por order).
 */
class SyncTenantOrderStates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries   = 1;

    // status_order_id del tenant → status del snapshot.
    // 1=Pendiente, 2=Verificado → 'confirmed' (no cambia, ya esta asi)
    // 3=Despachado → 'confirmed' (sigue sin "completar"; se completa con entrega)
    // 4=Entregado/Completado → 'completed'
    // 5=Cancelado/Anulado → 'cancelled'
    private const STATUS_MAP = [
        1 => 'confirmed',
        2 => 'confirmed',
        3 => 'confirmed',
        4 => 'completed',
        5 => 'cancelled',
    ];

    public function handle(): void
    {
        // Listamos sub-orders activos (no en estado final del snapshot)
        // con un marketplace_user_id asociado a la orden padre.
        $subs = DB::connection('system')->table('tenant_marketplace_orders as sub')
            ->join('marketplace_orders as mo', 'mo.id', '=', 'sub.marketplace_order_id')
            ->whereNotNull('sub.tenant_order_id')
            ->whereNotNull('mo.marketplace_user_id')
            ->select(
                'sub.hostname_id',
                'sub.tenant_order_id',
                'mo.marketplace_user_id',
                'mo.order_number'
            )
            ->get();

        if ($subs->isEmpty()) return;

        $byHost = $subs->groupBy('hostname_id');
        $tenancy = app(Environment::class);
        $previous = $tenancy->tenant();

        try {
            foreach ($byHost as $hostnameId => $rows) {
                $hostname = Hostname::find($hostnameId);
                if (!$hostname || !$hostname->website) continue;

                try {
                    $tenancy->tenant($hostname->website);
                    $orderIds = $rows->pluck('tenant_order_id')->all();
                    $tenantOrders = DB::connection('tenant')->table('orders')
                        ->whereIn('id', $orderIds)
                        ->select('id', 'status_order_id', 'total', 'updated_at')
                        ->get()
                        ->keyBy('id');

                    // Volver a system para escribir snapshot.
                    $tenancy->tenant(null);

                    foreach ($rows as $r) {
                        $tOrder = $tenantOrders->get($r->tenant_order_id);
                        if (!$tOrder) continue;
                        $newStatus = self::STATUS_MAP[$tOrder->status_order_id] ?? 'confirmed';

                        $existing = MarketplaceUserOrder::where('hostname_id', $r->hostname_id)
                            ->where('order_id', $r->tenant_order_id)
                            ->first();

                        // Sincronizar SOLO si difiere — evita updates innecesarios.
                        if ($existing && $existing->status === $newStatus) continue;

                        $confirmedAt = in_array($newStatus, ['confirmed', 'completed'])
                            ? ($existing->confirmed_at ?? now()) : null;
                        $cancelledAt = $newStatus === 'cancelled' ? now() : null;

                        \App\Jobs\Marketplace\PushOrderToSystem::dispatch(
                            (int) $r->marketplace_user_id,
                            (int) $r->hostname_id,
                            (int) $r->tenant_order_id,
                            (float) ($tOrder->total ?: ($existing->total ?? 0)),
                            $existing->currency ?? 'PEN',
                            $newStatus,
                            $confirmedAt ? $confirmedAt->toDateTimeString() : null,
                            $cancelledAt ? $cancelledAt->toDateTimeString() : null,
                            (int) ($existing->items_count ?? 0),
                            $existing->product_categories ?? [],
                        )->onQueue('default');
                    }
                } catch (\Throwable $e) {
                    Log::warning('SyncTenantOrderStates per-tenant fail', [
                        'hostname_id' => $hostnameId,
                        'err'         => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            $tenancy->tenant($previous ?: null);
        }
    }
}
