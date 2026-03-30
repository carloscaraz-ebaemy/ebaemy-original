<?php

namespace App\Models\Tenant;

use Hyn\Tenancy\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use UsesTenantConnection;

    protected $fillable = [
        'item_id', 'person_id', 'order_id', 'reviewer_name', 'reviewer_email',
        'rating', 'title', 'body', 'status', 'verified_purchase', 'admin_reply', 'approved_at',
    ];

    protected $casts = [
        'rating' => 'integer', 'verified_purchase' => 'boolean', 'approved_at' => 'datetime',
    ];

    public function item() { return $this->belongsTo(Item::class); }
    public function person() { return $this->belongsTo(Person::class); }
    public function order() { return $this->belongsTo(Order::class); }

    public function scopeApproved($q) { return $q->where('status', 'approved'); }
    public function scopePending($q) { return $q->where('status', 'pending'); }
    public function scopeForItem($q, $itemId) { return $q->where('item_id', $itemId); }

    public static function averageForItem(int $itemId): array
    {
        $reviews = static::approved()->forItem($itemId);
        return [
            'average' => round($reviews->avg('rating') ?? 0, 1),
            'count' => $reviews->count(),
            'distribution' => [
                5 => (clone $reviews)->where('rating', 5)->count(),
                4 => (clone $reviews)->where('rating', 4)->count(),
                3 => (clone $reviews)->where('rating', 3)->count(),
                2 => (clone $reviews)->where('rating', 2)->count(),
                1 => (clone $reviews)->where('rating', 1)->count(),
            ],
        ];
    }
}
