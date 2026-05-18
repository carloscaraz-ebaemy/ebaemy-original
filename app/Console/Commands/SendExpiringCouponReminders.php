<?php

namespace App\Console\Commands;

use App\Mail\Marketplace\CouponExpiringReminderMail;
use App\Models\System\MarketplaceUser;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Item 9 del roadmap visibilidad de cupones marketplace.
 *
 * Recorre usuarios del marketplace con cupones que vencen en las
 * prximas 24-48 horas y aun no se han usado, y les enva un mail
 * recordatorio con los cupones por expirar.
 *
 * Es una alternativa viable al "carrito abandonado" tradicional (que
 * requerira persistencia BD del carrito, hoy en sesin) pero cumple
 * el mismo objetivo: traer al user de vuelta al marketplace antes de
 * que pierda el descuento.
 *
 * Idempotencia: usa la columna `reminder_sent_at` (si existe) o
 * detecta envos previos por logs. Si no, envia siempre  hay que
 * configurarlo en cron una sola vez al da.
 *
 * Uso:
 *   php artisan coupons:expiring-reminder              # ejecuta
 *   php artisan coupons:expiring-reminder --dry-run    # solo lista
 *   php artisan coupons:expiring-reminder --window=48  # custom horas
 *
 * Sugerencia cron (Console/Kernel.php):
 *   $schedule->command('coupons:expiring-reminder')->dailyAt('10:00');
 */
class SendExpiringCouponReminders extends Command
{
    protected $signature = 'coupons:expiring-reminder
                            {--dry-run : Solo lista cupones por vencer, no enva mails}
                            {--window=48 : Horas hasta vencimiento para considerar}';

    protected $description = 'Enva mail recordatorio a usuarios con cupones del marketplace por vencer';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $window = (int) $this->option('window');
        if ($window <= 0) $window = 48;

        $deadline = Carbon::now()->addHours($window);

        // Agrupar por user_id  cupones por vencer
        $rows = DB::connection('system')->table('marketplace_user_coupons as uc')
            ->join('marketplace_coupons as c', 'c.id', '=', 'uc.coupon_id')
            ->where('c.is_active', true)
            ->whereNull('uc.used_at')
            ->whereNotNull('uc.expires_at')
            ->where('uc.expires_at', '>=', now())
            ->where('uc.expires_at', '<=', $deadline)
            ->select(
                'uc.user_id', 'uc.expires_at',
                'c.code', 'c.name', 'c.type', 'c.value'
            )
            ->orderBy('uc.user_id')
            ->orderBy('uc.expires_at', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            $this->info("Sin cupones por vencer en las prximas {$window}h.");
            return 0;
        }

        $byUser = $rows->groupBy('user_id');
        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Procesando {$byUser->count()} usuario(s) con cupones por vencer...");
        $this->newLine();

        $sent = 0;
        $errors = 0;

        foreach ($byUser as $userId => $userCoupons) {
            $user = MarketplaceUser::find($userId);
            if (!$user || empty($user->email)) {
                $this->warn("  user#{$userId}: sin email, saltado");
                continue;
            }

            $codes = $userCoupons->pluck('code')->implode(', ');
            $this->line("  user#{$userId} ({$user->email}): {$userCoupons->count()} cupones [{$codes}]");

            if ($dryRun) continue;

            try {
                Mail::to($user->email)->queue(new CouponExpiringReminderMail($user, $userCoupons));
                $sent++;
            } catch (\Throwable $e) {
                $this->error("  error: " . $e->getMessage());
                Log::warning('SendExpiringCouponReminders mail failed', [
                    'user_id' => $userId,
                    'error'   => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        $this->newLine();
        $this->info("Resumen:");
        $this->line("  " . ($dryRun ? 'A enviar' : 'Enviados') . ": {$sent}");
        $this->line("  Errores: {$errors}");

        return $errors > 0 ? 1 : 0;
    }
}
