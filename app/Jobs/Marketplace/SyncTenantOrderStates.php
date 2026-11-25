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

    // status_order_id del tenant → status del snapshot. Ids según la
    // migración tenant 2026_03_23_000004: 1 Pago pendiente, 2 Verificado,
    // 3 En preparación, 4 Enviado, 5 Cancelado, 6 Entregado. (El mapa viejo
    // daba 4 = completado y no conocía el 6: «Enviado» se cerraba y
    // «Entregado» retrocedía a confirmado.)
    private const STATUS_MAP = [
        1 => 'confirmed',
        2 => 'confirmed',
        3 => 'confirmed',
        4 => 'confirmed',
        5 => 'cancelled',
        6 => 'completed',
    ];

    // Y lo mismo hacia `tenant_marketplace_orders.status`, que es lo que ve el
    // comprador en su confirmación: hasta ahora nadie escribía `delivered` ni
    // `cancelled` para tiendas locales y el paquete decía «La tienda ya tiene
    // tu pedido» para siempre.
    private const SUB_STATUS_MAP = [
        5 => 'cancelled',
        6 => 'delivered',
    ];

    public function handle(): void
    {
        // Subpedidos despachados que aún no están en estado final (entregado
        // o cancelado), con o sin comprador registrado: el estado del paquete
        // lo ve también el comprador invitado en su confirmación. Solo los de
        // los últimos 60 días: lo anterior no va a cambiar.
        $subs = DB::connection('system')->table('tenant_marketplace_orders as sub')
            ->join('marketplace_orders as mo', 'mo.id', '=', 'sub.marketplace_order_id')
            ->whereNotNull('sub.tenant_order_id')
            ->whereNotIn('sub.status', ['delivered', 'cancelled'])
            ->where('sub.created_at', '>=', now()->subDays(60))
            ->select(
                'sub.id as sub_id',
                'sub.status as sub_status',
                'sub.hostname_id',
                'sub.tenant_order_id',
                'mo.id as mo_id',
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

                    $parentsToRecompute = [];
                    foreach ($rows as $r) {
                        $tOrder = $tenantOrders->get($r->tenant_order_id);
                        if (!$tOrder) continue;

                        // Estado del paquete para el comprador (tabla puente).
                        $subStatus = self::SUB_STATUS_MAP[(int) $tOrder->status_order_id] ?? null;
                        if ($subStatus && $subStatus !== $r->sub_status) {
                            DB::connection('system')->table('tenant_marketplace_orders')
                                ->where('id', $r->sub_id)
                                ->update(['status' => $subStatus, 'updated_at' => now()]);
                            $parentsToRecompute[$r->mo_id] = true;
                        }

                        // Snapshot del comprador registrado: solo si lo hay.
                        if (!$r->marketplace_user_id) continue;
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

                    foreach (array_keys($parentsToRecompute) as $moId) {
                        try {
                            \App\Models\System\MarketplaceOrder::find($moId)?->recomputeStatus();
                        } catch (\Throwable $_) {}
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
