<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Modules\Ecommerce\Http\Controllers\StockNotificationController;

class SendStockNotifications extends Command
{
    protected $signature   = 'ecommerce:send-stock-notifications';
    protected $description = 'Envía emails a los suscriptores de productos que volvieron a tener stock (todos los tenants)';

    public function handle(Environment $tenancy): int
    {
        $totalSent = 0;

        Website::all()->each(function (Website $website) use ($tenancy, &$totalSent) {
            try {
                $tenancy->tenant($website);
                $sent = StockNotificationController::dispatchPendingNotifications();
                $totalSent += $sent;

                if ($sent > 0) {
                    $this->line("Tenant [{$website->uuid}]: {$sent} notificación(es) enviada(s).");
                }
            } catch (\Throwable $e) {
                Log::error('[SendStockNotifications] Error en tenant', [
                    'tenant' => $website->uuid,
                    'error'  => $e->getMessage(),
                ]);
                $this->error("Error en tenant [{$website->uuid}]: {$e->getMessage()}");
            }
        });

        $this->info("Total notificaciones enviadas: {$totalSent}");
        return 0;
    }
}
