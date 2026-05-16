<?php

namespace App\Jobs\Marketplace;

use App\Models\System\MarketplaceUserView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Inserta una fila en marketplace_user_views.
 * SIEMPRE async — el render del detalle del producto NUNCA debe
 * frenarse por escribir tracking.
 *
 * Deduplicacion ligera: el caller (controller) puede saltarse el
 * dispatch si ya hubo una view del mismo (user, listing) en los
 * ultimos 60 segundos. Aqui solo insertamos.
 */
class RecordMarketplaceUserView implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 2;

    public function __construct(
        public int $userId,
        public int $listingId,
        public ?int $hostnameId,
        public ?string $sessionId,
        public ?string $referrer,
    ) {}

    public function handle(): void
    {
        MarketplaceUserView::create([
            'user_id'     => $this->userId,
            'hostname_id' => $this->hostnameId,
            'listing_id'  => $this->listingId,
            'viewed_at'   => now(),
            'session_id'  => $this->sessionId,
            'referrer'    => $this->referrer ? substr($this->referrer, 0, 255) : null,
        ]);
    }
}
