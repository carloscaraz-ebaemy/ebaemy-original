<?php

namespace App\Jobs\Marketplace;

use App\Mail\Marketplace\MarketplaceWeeklyDigestMail;
use App\Models\System\MarketplaceUser;
use App\Services\Marketplace\MarketplaceNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Digest semanal de ofertas en categorias favoritas del comprador.
 *
 * Schedule: domingo 10:00. Solo a users con:
 *  - status=active
 *  - email_frequency=weekly
 *  - consent email/marketing vigente
 *  - intereses calculados (al menos 1 categoria con score > 0)
 *
 * Selecciona top 6 ofertas activas en sus top 3 categorias.
 */
class SendWeeklyMarketplaceDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1200;
    public $tries   = 1;

    const TOP_CATEGORIES = 3;
    const OFFERS_PER_DIGEST = 6;

    public function handle(MarketplaceNotificationService $notif): void
    {
        $userIds = DB::connection('system')->table('marketplace_users as u')
            ->join('marketplace_user_preferences as p', 'p.user_id', '=', 'u.id')
            ->where('u.status', 'active')
            ->where('p.email_frequency', 'weekly')
            ->pluck('u.id');

        foreach ($userIds as $userId) {
            $user = MarketplaceUser::find($userId);
            if (!$user) continue;
            if (!$notif->canSendEmail($user, 'marketing')) continue;

            // Top categorias del user
            $topCats = DB::connection('system')->table('marketplace_user_interests as i')
                ->leftJoin('marketplace_categories as c', 'c.id', '=', 'i.category_id')
                ->where('i.user_id', $userId)
                ->orderByDesc('i.score')
                ->limit(self::TOP_CATEGORIES)
                ->select('i.category_id', 'c.name')
                ->get();
            if ($topCats->isEmpty()) continue;
            $catIds = $topCats->pluck('category_id')->all();
            $catNames = $topCats->pluck('name')->filter()->values()->all();

            // Ofertas activas en esas categorias.
            // is_on_offer + descuento por mp_price < price, ultimas 30d, ordenadas por descuento.
            $offers = DB::connection('system')->table('marketplace_listings')
                ->whereIn('marketplace_category_id', $catIds)
                ->where('is_active', true)
                ->where('status', 'active')
                ->where('stock', '>', 0)
                ->where('is_on_offer', true)
                ->whereRaw('mp_price IS NOT NULL AND mp_price < price')
                ->orderByDesc('discount_pct')
                ->limit(self::OFFERS_PER_DIGEST)
                ->select('title', 'slug', 'image_url', 'mp_price as price', 'price as original_price', 'discount_pct')
                ->get()
                ->map(fn ($o) => (array) $o)
                ->all();

            try {
                Mail::to($user->email)->send(new MarketplaceWeeklyDigestMail($user, $offers, $catNames));
            } catch (\Throwable $e) {
                logger()->warning('WeeklyDigest mail failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            }
        }
    }
}
