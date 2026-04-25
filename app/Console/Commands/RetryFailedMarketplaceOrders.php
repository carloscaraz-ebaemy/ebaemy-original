<?php

namespace App\Console\Commands;

use App\Models\System\MarketplaceOrder;
use App\Models\System\TenantMarketplaceOrder;
use App\Services\System\MarketplaceMultiOrderDispatcher;
use Illuminate\Console\Command;

/**
 * Reintenta el dispatch de subpedidos `tenant_marketplace_orders` con
 * status=failed cuyo retry_count esté por debajo del límite.
 *
 * Uso:
 *   php artisan marketplace:retry-failed-orders
 *   php artisan marketplace:retry-failed-orders --max-retries=5 --limit=50
 *
 * Pensado para ser programado en Kernel cada 15 min — los tenants pueden
 * estar caídos al momento del checkout y resolverse luego.
 */
class RetryFailedMarketplaceOrders extends Command
{
    protected $signature = 'marketplace:retry-failed-orders
        {--max-retries=3 : No reintentar subpedidos con retry_count >= este valor}
        {--limit=50 : Cantidad máxima de pedidos a procesar en esta corrida}';

    protected $description = 'Reintenta el dispatch de subpedidos del marketplace que fallaron previamente';

    public function handle(MarketplaceMultiOrderDispatcher $dispatcher): int
    {
        $maxRetries = max(1, (int) $this->option('max-retries'));
        $limit      = max(1, (int) $this->option('limit'));

        $orderIds = TenantMarketplaceOrder::query()
            ->where('status', 'failed')
            ->where('retry_count', '<', $maxRetries)
            ->orderBy('updated_at')
            ->limit($limit)
            ->pluck('marketplace_order_id')
            ->unique()
            ->values();

        if ($orderIds->isEmpty()) {
            $this->info('No hay subpedidos failed con reintentos disponibles.');
            return self::SUCCESS;
        }

        $this->info("Reintentando {$orderIds->count()} pedido(s)…");

        $totalOk = 0;
        $totalFail = 0;

        foreach ($orderIds as $orderId) {
            $order = MarketplaceOrder::find($orderId);
            if (!$order) {
                continue;
            }

            // Pasar failed → pending para que el dispatcher los procese
            TenantMarketplaceOrder::query()
                ->where('marketplace_order_id', $order->id)
                ->where('status', 'failed')
                ->where('retry_count', '<', $maxRetries)
                ->update(['status' => 'pending']);

            $result = $dispatcher->dispatchOrder($order->refresh());
            $totalOk   += $result['success_count'] ?? 0;
            $totalFail += $result['failed_count']  ?? 0;

            $this->line("  {$order->order_number}: OK={$result['success_count']} FAIL={$result['failed_count']}");
        }

        $this->info("Resumen: {$totalOk} subpedido(s) despachado(s), {$totalFail} sigue(n) fallando.");
        return self::SUCCESS;
    }
}
