<?php

namespace App\Jobs\Marketplace;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula marketplace_user_interests para todos los users activos
 * con actividad en los ultimos 90 dias.
 *
 * Formula:
 *   score = views_30d + (favorites * 5) + (purchases * 30)
 * con decay exponencial: cada accion vale * exp(-dias/30).
 *
 * Cart_adds del plan original: pendiente — el cart vive en sesion y
 * no se persiste por user. Se sumara cuando exista marketplace_cart_events.
 *
 * Schedule sugerido: diario 3am (Console\Kernel.php).
 */
class RecalculateMarketplaceUserInterests implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries   = 1;

    public function handle(): void
    {
        // Users activos en los ultimos 90 dias.
        $userIds = DB::connection('system')->table('marketplace_users')
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('last_seen_at', '>=', now()->subDays(90))
                  ->orWhere('last_login_at', '>=', now()->subDays(90));
            })
            ->pluck('id');

        foreach ($userIds as $userId) {
            $this->recalcUser((int) $userId);
        }
    }

    private function recalcUser(int $userId): void
    {
        $now = now();

        // VIEWS: agregado por category_name → category_id mapping.
        // Como marketplace_listings tiene marketplace_category_id que mapea
        // a la taxonomia oficial, joinamos por ahi.
        $viewRows = DB::connection('system')->table('marketplace_user_views as v')
            ->join('marketplace_listings as l', 'l.id', '=', 'v.listing_id')
            ->where('v.user_id', $userId)
            ->where('v.viewed_at', '>=', $now->copy()->subDays(30))
            ->whereNotNull('l.marketplace_category_id')
            ->select('l.marketplace_category_id as cat_id', 'v.viewed_at')
            ->get();

        $favRows = DB::connection('system')->table('marketplace_favorites as f')
            ->join('marketplace_listings as l', 'l.id', '=', 'f.listing_id')
            ->where('f.user_id', $userId)
            ->whereNotNull('l.marketplace_category_id')
            ->select('l.marketplace_category_id as cat_id', 'f.created_at as at')
            ->get();

        $orderRows = DB::connection('system')->table('marketplace_user_orders')
            ->where('user_id', $userId)
            ->whereNotNull('confirmed_at')
            ->whereNotNull('product_categories')
            ->get(['product_categories', 'confirmed_at']);

        $scoreByCat = [];
        $addScore = function (int $catId, float $weight, $atTs) use (&$scoreByCat, $now) {
            $ageDays = $atTs ? max(0, $now->diffInDays($atTs, false) * -1) : 0;
            $decay = exp(-$ageDays / 30);  // half-life ~21 dias
            $scoreByCat[$catId] = ($scoreByCat[$catId] ?? 0) + $weight * $decay;
        };

        foreach ($viewRows as $r) {
            $addScore((int) $r->cat_id, 1.0, $r->viewed_at);
        }
        foreach ($favRows as $r) {
            $addScore((int) $r->cat_id, 5.0, $r->at);
        }
        foreach ($orderRows as $r) {
            $cats = json_decode($r->product_categories, true) ?: [];
            foreach ($cats as $catId) {
                if (is_numeric($catId)) {
                    $addScore((int) $catId, 30.0, $r->confirmed_at);
                }
            }
        }

        // Upsert por (user_id, category_id). Antes, limpiamos los que ya
        // no aplican (score < 1.0 — irrelevantes).
        DB::connection('system')->transaction(function () use ($userId, $scoreByCat, $now) {
            DB::connection('system')->table('marketplace_user_interests')
                ->where('user_id', $userId)
                ->delete();

            $rows = [];
            foreach ($scoreByCat as $catId => $score) {
                if ($score < 1.0) continue;
                $rows[] = [
                    'user_id'              => $userId,
                    'category_id'          => $catId,
                    'score'                => round($score, 2),
                    'last_recalculated_at' => $now,
                ];
            }
            if (!empty($rows)) {
                // Chunk de 500 para no inflar el packet.
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::connection('system')->table('marketplace_user_interests')->insert($chunk);
                }
            }
        });
    }
}
