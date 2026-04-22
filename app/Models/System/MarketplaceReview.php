<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Review cross-tenant del marketplace central (ebaemy.com/marketplace).
 * Cada review apunta a un MarketplaceListing específico. Los promedios se
 * denormalizan en marketplace_listings (avg_rating, rating_count) al aprobar
 * o rechazar un review — ver MarketplaceReview::recalculateListingStats().
 */
class MarketplaceReview extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_reviews';

    protected $fillable = [
        'listing_id',
        'hostname_id',
        'customer_name',
        'customer_email',
        'rating',
        'comment',
        'status',
        'approved_at',
        'rejection_reason',
        'source_ip',
        'source_ua',
    ];

    protected $casts = [
        'rating'       => 'integer',
        'approved_at'  => 'datetime',
    ];

    public function listing()
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Recalcula avg_rating y rating_count del listing asociado. Se llama
     * después de aprobar/rechazar/eliminar un review.
     */
    public static function recalculateListingStats(int $listingId): void
    {
        $stats = self::where('listing_id', $listingId)
            ->where('status', 'approved')
            ->selectRaw('AVG(rating) as avg, COUNT(*) as cnt')
            ->first();

        MarketplaceListing::where('id', $listingId)->update([
            'avg_rating'   => round((float) ($stats->avg ?? 0), 2),
            'rating_count' => (int) ($stats->cnt ?? 0),
        ]);
    }
}
