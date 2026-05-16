<?php

namespace App\Jobs\Marketplace;

use App\Mail\Marketplace\MarketplacePriceDropMail;
use App\Models\System\MarketplaceUser;
use App\Services\Marketplace\MarketplaceNotificationService;
use App\Services\Marketplace\MarketplaceWhatsAppNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Detecta favoritos con price_snapshot > current_price y dispara un
 * email por user con los drops agrupados.
 *
 * Pasa por MarketplaceNotificationService.canSendEmail() para validar
 * consent + preferences. Tras enviar, actualiza price_snapshot al
 * precio nuevo para no re-enviar el mismo drop.
 *
 * Schedule sugerido: diario 09:00 (no muy temprano para llegar en
 * horario de mailbox; las bajadas suelen ocurrir overnight con sync).
 *
 * Solo procesa drops >= 5% para no spamear con micro-cambios.
 */
class DetectAndNotifyPriceDrops implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries   = 1;

    const MIN_DROP_PCT = 5.0;
    const MAX_DROPS_PER_EMAIL = 10;

    public function handle(MarketplaceNotificationService $notif, MarketplaceWhatsAppNotifier $wa): void
    {
        // Favoritos con drop real (snapshot - actual > 0).
        $rows = DB::connection('system')->table('marketplace_favorites as f')
            ->join('marketplace_listings as l', 'l.id', '=', 'f.listing_id')
            ->whereNotNull('f.user_id')
            ->whereNotNull('f.price_snapshot')
            ->whereRaw('COALESCE(l.mp_price, l.price) < f.price_snapshot')
            ->where('l.is_active', true)
            ->where('l.status', 'active')
            ->select(
                'f.id as fav_id', 'f.user_id', 'f.price_snapshot',
                'l.id as listing_id', 'l.title', 'l.slug', 'l.image_url',
                DB::raw('COALESCE(l.mp_price, l.price) as new_price')
            )
            ->get();

        // Agrupar por user
        $byUser = $rows->groupBy('user_id');
        foreach ($byUser as $userId => $userDrops) {
            $user = MarketplaceUser::find($userId);
            if (!$user || !$user->isActive()) continue;

            $drops = $userDrops
                ->map(function ($r) {
                    $oldP = (float) $r->price_snapshot;
                    $newP = (float) $r->new_price;
                    $pct  = $oldP > 0 ? round((($oldP - $newP) / $oldP) * 100, 1) : 0;
                    if ($pct < self::MIN_DROP_PCT) return null;
                    return [
                        'fav_id'     => $r->fav_id,
                        'title'      => $r->title,
                        'slug'       => $r->slug,
                        'image_url'  => $r->image_url,
                        'old_price'  => $oldP,
                        'new_price'  => $newP,
                        'saving_pct' => $pct,
                    ];
                })
                ->filter()
                ->sortByDesc('saving_pct')
                ->take(self::MAX_DROPS_PER_EMAIL)
                ->values()
                ->all();

            if (empty($drops)) continue;

            $sentSomething = false;

            // Email (con su propio consent gating)
            if ($notif->canSendEmail($user, 'price_alerts')) {
                try {
                    Mail::to($user->email)->send(new MarketplacePriceDropMail($user, $drops));
                    $sentSomething = true;
                } catch (\Throwable $e) {
                    logger()->warning('PriceDrop mail failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }

            // WhatsApp (consent independiente del email).
            try {
                if ($wa->sendPriceDrop($user, $drops)) {
                    $sentSomething = true;
                }
            } catch (\Throwable $e) {
                logger()->warning('PriceDrop WA failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }

            if (!$sentSomething) continue;

            // Refresh snapshot para no re-mandar (solo si al menos un canal envio)
            $favIds = array_column($drops, 'fav_id');
            DB::connection('system')->table('marketplace_favorites')
                ->whereIn('id', $favIds)
                ->update([
                    'price_snapshot' => DB::raw('(SELECT COALESCE(mp_price, price) FROM marketplace_listings WHERE marketplace_listings.id = marketplace_favorites.listing_id)'),
                    'updated_at' => now(),
                ]);
        }
    }
}
