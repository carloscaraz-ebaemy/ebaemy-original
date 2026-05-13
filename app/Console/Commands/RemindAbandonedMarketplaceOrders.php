<?php

namespace App\Console\Commands;

use App\Mail\MarketplaceAbandonedOrderMail;
use App\Models\System\MarketplaceOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Recordatorio por email a compradores que abandonaron un pedido marketplace.
 *
 * Criterios para enviar:
 *   - status = pending
 *   - payment_status = unpaid (o NULL)
 *   - customer_email NOT NULL
 *   - created_at entre 2h y 7 días atrás
 *   - reminder_count < 2
 *   - reminder_sent_at NULL  o  > 24h atrás
 *
 * Idempotente: si nada califica, no envía nada. Logs cada envío en
 * laravel.log para auditoría.
 *
 * Scheduling: registrar en Kernel para correr cada hora.
 *   $schedule->command('marketplace:remind-abandoned-orders')->hourly();
 *
 * Uso manual:
 *   php artisan marketplace:remind-abandoned-orders --dry-run
 *   php artisan marketplace:remind-abandoned-orders
 */
class RemindAbandonedMarketplaceOrders extends Command
{
    protected $signature = 'marketplace:remind-abandoned-orders
                            {--dry-run : Solo lista candidatos, no envía}
                            {--limit=50 : Máximo de envíos por corrida}';

    protected $description = 'Email recordatorio a pedidos marketplace abandonados (pending + unpaid)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit  = max(1, (int) $this->option('limit'));

        $now = now();
        $minAge  = $now->copy()->subHours(2);   // espera 2h antes del 1er recordatorio
        $maxAge  = $now->copy()->subDays(7);    // ignora pedidos viejos (>7d)
        $cooldown = $now->copy()->subHours(24); // entre recordatorios

        $candidates = MarketplaceOrder::query()
            ->where('status', MarketplaceOrder::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('payment_status')->orWhere('payment_status', 'unpaid');
            })
            ->whereNotNull('customer_email')
            ->where('customer_email', '!=', '')
            ->where('created_at', '<=', $minAge)
            ->where('created_at', '>=', $maxAge)
            ->where('reminder_count', '<', 2)
            ->where(function ($q) use ($cooldown) {
                $q->whereNull('reminder_sent_at')->orWhere('reminder_sent_at', '<=', $cooldown);
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No hay pedidos abandonados que califiquen.');
            return 0;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Candidatos: {$candidates->count()}");

        $sent = 0; $failed = 0;
        foreach ($candidates as $order) {
            $this->line(sprintf(
                '  • %s (%s) — %s — total S/ %.2f — recordatorios previos: %d',
                $order->order_number,
                $order->customer_email,
                $order->created_at->diffForHumans(),
                (float) $order->total,
                (int) $order->reminder_count
            ));

            if ($dryRun) continue;

            try {
                Mail::to($order->customer_email)
                    ->send(new MarketplaceAbandonedOrderMail($order));

                $order->update([
                    'reminder_sent_at' => now(),
                    'reminder_count'   => ($order->reminder_count ?? 0) + 1,
                ]);

                $sent++;
                Log::info('marketplace abandoned-order reminder sent', [
                    'order' => $order->order_number,
                    'email' => $order->customer_email,
                    'count' => $order->reminder_count + 1,
                ]);
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('marketplace abandoned-order reminder failed', [
                    'order' => $order->order_number,
                    'email' => $order->customer_email,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("    ✗ falló: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Enviados: {$sent} · Fallidos: {$failed}");

        return $failed > 0 ? 1 : 0;
    }
}
