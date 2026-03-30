<?php

namespace App\Console\Commands;

use App\Models\Tenant\Order;
use App\Services\Tenant\WhatsAppService;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;

/**
 * Envía recordatorio por WhatsApp de pedidos pendientes.
 * Se ejecuta cada hora via scheduler.
 *
 * Uso: php artisan orders:remind-pending
 */
class RemindPendingOrders extends Command
{
    protected $signature = 'orders:remind-pending';
    protected $description = 'Enviar recordatorio WhatsApp de pedidos pendientes de pago';

    public function handle(): int
    {
        $websites = Website::all();

        foreach ($websites as $website) {
            try {
                $env = app(\Hyn\Tenancy\Environment::class);
                $env->tenant($website);

                $this->processTenan($website->uuid);
            } catch (\Throwable $e) {
                $this->warn("Error en tenant {$website->uuid}: {$e->getMessage()}");
            }
        }

        return 0;
    }

    protected function processTenan(string $uuid): void
    {
        $wa = new WhatsAppService();
        if (!$wa->isEnabled()) return;

        // Leer intervalo configurado por el tenant
        $ecomConfig = \App\Models\Tenant\ConfigurationEcommerce::first();
        $interval = $ecomConfig->notification_interval ?? 5;
        if (!($ecomConfig->notify_pending_reminder ?? true)) return;

        // No enviar si ya se envió dentro del intervalo
        $cacheKey = "pending_reminder_sent_{$uuid}";
        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) return;

        $pendingOrders = Order::where('status_order_id', 1)
            ->where('created_at', '<', now()->subMinutes($interval))
            ->get();

        if ($pendingOrders->isEmpty()) return;

        // Marcar como enviado por el intervalo configurado
        \Illuminate\Support\Facades\Cache::put($cacheKey, true, now()->addMinutes($interval));

        $company = \App\Models\Tenant\Company::first();
        $storeName = $company->trade_name ?? $company->name ?? 'Tienda';

        // Construir resumen
        $count = $pendingOrders->count();
        $totalAmount = $pendingOrders->sum('total');

        $details = '';
        foreach ($pendingOrders->take(10) as $order) {
            $orderId = strtoupper(substr($order->external_id, 0, 8));
            $customer = is_array($order->customer) ? $order->customer : (array) $order->customer;
            $name = $customer['apellidos_y_nombres_o_razon_social'] ?? 'Sin nombre';
            $hours = now()->diffInHours($order->created_at);
            $details .= "  • #{$orderId} — {$name} — S/ " . number_format($order->total, 2) . " ({$hours}h)\n";
        }
        if ($count > 10) {
            $details .= "  ... y " . ($count - 10) . " más\n";
        }

        $msg = "⏰ *RECORDATORIO: {$count} PEDIDOS PENDIENTES*\n\n"
             . "Tienda: *{$storeName}*\n"
             . "Total pendiente: *S/ " . number_format($totalAmount, 2) . "*\n\n"
             . "Pedidos:\n{$details}\n"
             . "👉 Revisa y confirma los pagos en el panel de administración.";

        // Enviar al admin (usa vendorPhone configurado internamente)
        $ecomConfig = \App\Models\Tenant\ConfigurationEcommerce::first();
        $adminPhone = $ecomConfig->phone_whatsapp ?? null;
        if ($adminPhone) {
            $wa->send($adminPhone, $msg);
        }

        $this->info("Tenant {$uuid}: {$count} pedidos pendientes, recordatorio enviado.");
    }
}
