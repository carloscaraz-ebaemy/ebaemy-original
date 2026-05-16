<?php

namespace App\Jobs\Marketplace;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Retencion 90 dias de marketplace_user_views.
 * Chunkeado para no bloquear la BD con un DELETE grande.
 */
class PurgeOldMarketplaceUserViews implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;
    public $tries   = 1;

    public function handle(): void
    {
        $cutoff = now()->subDays(90);
        do {
            $deleted = DB::connection('system')->table('marketplace_user_views')
                ->where('viewed_at', '<', $cutoff)
                ->limit(2000)
                ->delete();
        } while ($deleted >= 2000);
    }
}
