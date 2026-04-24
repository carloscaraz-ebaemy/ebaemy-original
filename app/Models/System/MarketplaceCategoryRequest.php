<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de un seller para crear una nueva categoría oficial en el
 * marketplace, cuando no encuentra una adecuada al publicar un producto.
 *
 * El SuperAdmin la revisa desde /admin/marketplace/category-requests y
 * decide aprobar (lo cual crea una fila en marketplace_categories) o
 * rechazar con motivo.
 */
class MarketplaceCategoryRequest extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_category_requests';

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'product_id',
        'suggested_name',
        'suggested_parent_id',
        'description',
        'status',
        'admin_response',
        'reviewed_by',
        'reviewed_at',
        'created_marketplace_category_id',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    // ─────────────────────────────────────────────────────────
    //  Relaciones
    // ─────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function suggestedParent(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'suggested_parent_id');
    }

    public function resultingCategory(): BelongsTo
    {
        return $this->belongsTo(MarketplaceCategory::class, 'created_marketplace_category_id');
    }

    // ─────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────

    public function isPending(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isApproved(): bool { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool { return $this->status === self::STATUS_REJECTED; }

    public function scopePending($query)  { return $query->where('status', self::STATUS_PENDING); }
    public function scopeApproved($query) { return $query->where('status', self::STATUS_APPROVED); }
    public function scopeRejected($query) { return $query->where('status', self::STATUS_REJECTED); }
}
