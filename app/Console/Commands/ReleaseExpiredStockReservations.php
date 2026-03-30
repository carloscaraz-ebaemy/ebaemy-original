<?php

namespace App\Console\Commands;

use App\Enums\StockMovementTypeEnum;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\ItemVariantWarehouse;
use App\Models\Tenant\LogisticOrder;
use App\Models\Tenant\Order;
use App\Models\Tenant\StockMovement;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Libera reservas de stock (stock_committed) de checkouts ecommerce abandonados.
 *
 * Se ejecuta cada 30 minutos vía scheduler.
 * Una orden PENDING de ecommerce que supere --minutes minutos sin actualizar
 * se cancela y su stock_committed es liberado.
 */
class ReleaseExpiredStockReservations extends Command
{
    protected $signature   = 'stock:release-expired {--minutes=60 : Minutos de inactividad antes de liberar}';
    protected $description = 'Libera reservas de stock de checkouts ecommerce abandonados';

    public function handle(Environment $tenancy): int
    {
        $minutes = (int) $this->option('minutes');
        $totalReleased = 0;

        // chunk(20) evita cargar todos los tenants en memoria de una sola vez.
        // Si un tenant falla, los siguientes siguen procesándose normalmente.
        Website::chunk(20, function ($websites) use ($tenancy, $minutes, &$totalReleased) {
            foreach ($websites as $website) {
                try {
                    $tenancy->tenant($website);
                    $released = $this->releaseForTenant($minutes);
                    $totalReleased += $released;

                    if ($released > 0) {
                        $this->line("Tenant [{$website->uuid}]: {$released} orden(es) expirada(s) liberadas.");
                    }
                } catch (\Throwable $e) {
                    Log::error('[ReleaseExpiredStock] Error en tenant', [
                        'tenant' => $website->uuid,
                        'error'  => $e->getMessage(),
                    ]);
                    $this->error("Error en tenant [{$website->uuid}]: {$e->getMessage()}");
                } finally {
                    $tenancy->tenant(null);
                }
            }
        });

        $this->info("Total órdenes liberadas: {$totalReleased}");
        return 0;
    }

    private function releaseForTenant(int $minutes): int
    {
        // Release PENDING orders after configured minutes
        $expiredPending = LogisticOrder::where('source', 'ecommerce')
            ->where('status', 'pending')
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->get();

        // Release IN_PREPARATION orders stuck for 4+ hours
        $expiredPrep = LogisticOrder::where('source', 'ecommerce')
            ->where('status', 'in_preparation')
            ->where('updated_at', '<', now()->subHours(4))
            ->get();

        $expired = $expiredPending->merge($expiredPrep);

        // Liberar stock_committed de variantes en órdenes ecommerce simples expiradas (status=1)
        $this->releaseVariantStockForExpiredOrders($minutes);

        $released = 0;

        foreach ($expired as $order) {
            try {
                DB::transaction(function () use ($order) {
                    foreach ($order->items as $orderItem) {
                        $warehouseId = $orderItem->warehouse_id ?? $order->warehouse_id;

                        if (!$warehouseId) continue;

                        $iw = ItemWarehouse::where('item_id', $orderItem->item_id)
                                           ->where('warehouse_id', $warehouseId)
                                           ->lockForUpdate()
                                           ->first();

                        if (!$iw) continue;

                        $iw->applyStockMovement(
                            StockMovementTypeEnum::ECOMMERCE_CANCEL,
                            (float) $orderItem->quantity
                        );

                        StockMovement::record(
                            $iw,
                            StockMovementTypeEnum::ECOMMERCE_CANCEL,
                            (float) $orderItem->quantity,
                            null,
                            $order,
                            "Liberación automática — checkout expirado > {$this->option('minutes')}min"
                        );
                    }

                    $order->update([
                        'status'        => 'cancelled',
                        'cancel_reason' => 'Checkout no completado en tiempo límite',
                        'cancelled_at'  => now(),
                    ]);
                });

                $released++;

                Log::info('[ReleaseExpiredStock] Orden expirada cancelada', [
                    'order_id'        => $order->id,
                    'source'          => $order->source,
                    'previous_status' => $order->getOriginal('status'),
                    'idle_mins'       => now()->diffInMinutes($order->updated_at),
                ]);
            } catch (\Throwable $e) {
                Log::error('[ReleaseExpiredStock] Error liberando orden', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $released;
    }

    private function releaseVariantStockForExpiredOrders(int $minutes): void
    {
        // Solo órdenes Culqi (pago con tarjeta) en estado 1 expiradas
        // Las órdenes en efectivo quedan en estado 1 hasta confirmación manual — no cancelar automáticamente
        $expired = Order::where('status_order_id', 1)
            ->where('reference_payment', 'culqi')
            ->where('updated_at', '<', now()->subMinutes($minutes))
            ->get();

        foreach ($expired as $order) {
            try {
                DB::transaction(function () use ($order) {
                    $items = is_array($order->items) ? $order->items : (array) $order->items;
                    foreach ($items as $item) {
                        $item = (array) $item;
                        $variantId = $item['variant_id'] ?? null;
                        if (!$variantId) continue;
                        $qty = (float)($item['quantity'] ?? 1);

                        $vw = ItemVariantWarehouse::where('item_variant_id', $variantId)
                            ->lockForUpdate()->first();
                        if ($vw && $vw->stock_committed > 0) {
                            $vw->stock_committed = max(0, $vw->stock_committed - $qty);
                            $vw->save();
                        }
                    }
                    // Marcar la orden como cancelada por expiración
                    $order->update(['status_order_id' => 5]);
                });

                Log::info('[ReleaseExpiredStock] Orden ecommerce con variantes expirada cancelada', [
                    'order_id'  => $order->id,
                    'idle_mins' => now()->diffInMinutes($order->updated_at),
                ]);
            } catch (\Throwable $e) {
                Log::error('[ReleaseExpiredStock] Error liberando variantes de orden', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }
}
