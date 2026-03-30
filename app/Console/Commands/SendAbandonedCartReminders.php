<?php

namespace App\Console\Commands;

use App\Mail\Tenant\AbandonedCartReminderEmail;
use App\Models\Tenant\AbandonedCart;
use App\Models\Tenant\Company;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Envía recordatorios por email a clientes con carritos abandonados.
 *
 * Secuencia de 3 pasos:
 *   Paso 1: 1 hora después del abandono (recordatorio suave)
 *   Paso 2: 24 horas después (urgencia + stock limitado)
 *   Paso 3: 72 horas después (incentivo con descuento del 10%)
 *
 * Se programa cada hora via Kernel.php.
 *
 * Uso:
 *   php artisan cart:send-reminders
 *   php artisan cart:send-reminders --dry-run
 */
class SendAbandonedCartReminders extends Command
{
    protected $signature = 'cart:send-reminders
        {--dry-run : Simular sin enviar emails}';

    protected $description = 'Envía recordatorios por email a clientes con carritos abandonados (secuencia de 3 pasos).';

    public function handle(Environment $tenancy): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stats = ['step1' => 0, 'step2' => 0, 'step3' => 0, 'skipped' => 0];

        Website::chunk(10, function ($websites) use ($tenancy, $dryRun, &$stats) {
            foreach ($websites as $website) {
                try {
                    $tenancy->tenant($website);

                    $company   = Company::first();
                    $storeName = $company?->trade_name ?? $company?->name ?? 'Nuestra tienda';
                    $storeUrl  = 'https://' . (optional(optional($website->hostnames->first())->fqdn) ?? request()->getHost());

                    // ── Paso 1: 1 hora, sin reminders previos ──────────────────
                    $step1 = AbandonedCart::active()
                        ->whereNotNull('customer_email')
                        ->where('reminder_count', 0)
                        ->where('created_at', '<=', now()->subHour())
                        ->where('created_at', '>=', now()->subHours(24))
                        ->get();

                    foreach ($step1 as $cart) {
                        $this->sendReminder($cart, 1, $storeName, $storeUrl, $dryRun, $stats);
                    }

                    // ── Paso 2: 24 horas, 1 reminder enviado ───────────────────
                    $step2 = AbandonedCart::active()
                        ->whereNotNull('customer_email')
                        ->where('reminder_count', 1)
                        ->where('last_reminder_at', '<=', now()->subHours(23))
                        ->get();

                    foreach ($step2 as $cart) {
                        $this->sendReminder($cart, 2, $storeName, $storeUrl, $dryRun, $stats);
                    }

                    // ── Paso 3: 72 horas, 2 reminders enviados (con descuento) ─
                    $step3 = AbandonedCart::active()
                        ->whereNotNull('customer_email')
                        ->where('reminder_count', 2)
                        ->where('last_reminder_at', '<=', now()->subHours(48))
                        ->get();

                    foreach ($step3 as $cart) {
                        // Generar codigo de descuento unico
                        $discountCode = 'VUELVE' . strtoupper(Str::random(6));
                        $cart->discount_code = $discountCode;
                        $this->sendReminder($cart, 3, $storeName, $storeUrl, $dryRun, $stats);
                    }

                } catch (\Throwable $e) {
                    Log::error('[SendAbandonedCartReminders] Error en tenant.', [
                        'tenant' => $website->uuid,
                        'error'  => $e->getMessage(),
                    ]);
                } finally {
                    $tenancy->tenant(null);
                }
            }
        });

        $this->info("Paso 1 (recordatorio suave): {$stats['step1']}");
        $this->info("Paso 2 (urgencia): {$stats['step2']}");
        $this->info("Paso 3 (descuento): {$stats['step3']}");
        $this->info("Omitidos/errores: {$stats['skipped']}");
        return self::SUCCESS;
    }

    private function sendReminder(AbandonedCart $cart, int $step, string $storeName, string $storeUrl, bool $dryRun, array &$stats): void
    {
        // Re-check to avoid TOCTOU race condition
        $freshCart = AbandonedCart::find($cart->id);
        if (!$freshCart || $freshCart->recovered_at !== null) {
            $stats['skipped']++;
            return;
        }

        $stepKey = "step{$step}";
        $subjects = [
            1 => '¡Olvidaste algo en tu carrito!',
            2 => '¡Tu carrito te espera! Stock limitado',
            3 => '¡10% OFF solo para ti! Completa tu compra',
        ];

        if ($dryRun) {
            $this->line("  [dry-run] Paso {$step} -> {$freshCart->customer_email} ({$freshCart->item_count} items, S/{$freshCart->subtotal})");
            $stats['skipped']++;
            return;
        }

        try {
            $discountCode = $step === 3 ? ($cart->discount_code ?? null) : null;

            Mail::to($freshCart->customer_email)
                ->send(new AbandonedCartReminderEmail(
                    $freshCart,
                    $storeName,
                    $storeUrl,
                    $step,
                    $discountCode
                ));

            $updateData = [
                'reminder_sent_at' => now(),
                'reminder_count'   => $step,
                'last_reminder_at' => now(),
            ];
            if ($discountCode) {
                $updateData['discount_code'] = $discountCode;
            }
            $freshCart->update($updateData);

            $stats[$stepKey]++;
            $this->line("  Paso {$step} enviado a {$freshCart->customer_email}");
        } catch (\Exception $e) {
            $stats['skipped']++;
            Log::warning('[SendAbandonedCartReminders] Error enviando paso ' . $step, [
                'error' => $e->getMessage(),
                'email' => $freshCart->customer_email,
            ]);
        }
    }
}
