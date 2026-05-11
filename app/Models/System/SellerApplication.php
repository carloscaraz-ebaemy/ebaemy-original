<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Solicitud de onboarding de seller.
 *
 * Representa una solicitud enviada desde el formulario público
 * /seller/register. La solicitud pasa por un workflow de estados
 * (pending → under_review → approved/rejected) controlado por el
 * SuperAdmin desde el panel.
 *
 * Al aprobar, SellerApplicationService llama a TenantCreationService
 * con los datos de esta solicitud y vincula el tenant_id resultante.
 */
class SellerApplication extends Model
{
    use UsesSystemConnection;

    protected $table = 'seller_applications';

    // ── Estados del workflow ─────────────────────────────────
    public const STATUS_PENDING            = 'pending';
    public const STATUS_UNDER_REVIEW       = 'under_review';
    public const STATUS_REQUIRES_DOCUMENTS = 'requires_documents';
    public const STATUS_REQUIRES_REVIEW    = 'requires_review';
    public const STATUS_APPROVING          = 'approving';   // intermedio: tenant en proceso de creación
    public const STATUS_APPROVED           = 'approved';
    public const STATUS_REJECTED           = 'rejected';
    public const STATUS_CANCELLED          = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_REQUIRES_DOCUMENTS,
        self::STATUS_REQUIRES_REVIEW,
        self::STATUS_APPROVING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    // Estados en los que un RUC/email/subdominio se consideran "en uso"
    // para propósitos de validación de duplicados en nuevas solicitudes.
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_REQUIRES_DOCUMENTS,
        self::STATUS_REQUIRES_REVIEW,
        self::STATUS_APPROVING,
        self::STATUS_APPROVED,
    ];

    protected $fillable = [
        'ruc',
        'business_name',
        'trade_name',
        'category_id',
        'fiscal_address',
        'department_id',
        'province_id',
        'district_id',
        'legal_representative_name',
        'legal_representative_dni',
        'legal_representative_position',
        'email',
        'phone',
        'requested_subdomain',
        'store_name',
        'store_description',
        'password_hash',
        'logo_path',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'website_url',
        'ruc_status',
        'ruc_condition',
        'ruc_validation_response',
        'status',
        'rejection_reason',
        'review_notes',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'tenant_id',
        'is_activation_request',
        'tracking_token',
        'source_ip',
        'source_ua',
    ];

    protected $casts = [
        'ruc_validation_response' => 'array',
        'reviewed_at'             => 'datetime',
        'approved_at'             => 'datetime',
        'is_activation_request'   => 'boolean',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Genera un token opaco para el portal público de seguimiento.
     * Se llena al crear la solicitud y nunca cambia.
     */
    public static function generateTrackingToken(): string
    {
        return Str::random(48);
    }

    // ── Relaciones ────────────────────────────────────────────
    public function logs(): HasMany
    {
        return $this->hasMany(SellerApplicationLog::class, 'seller_application_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'tenant_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // ── Helpers de estado ────────────────────────────────────
    public function isPending(): bool        { return $this->status === self::STATUS_PENDING; }
    public function isUnderReview(): bool    { return $this->status === self::STATUS_UNDER_REVIEW; }
    public function isApproved(): bool       { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool       { return $this->status === self::STATUS_REJECTED; }

    public function isReviewable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_UNDER_REVIEW,
            self::STATUS_REQUIRES_DOCUMENTS,
            self::STATUS_REQUIRES_REVIEW,
        ], true);
    }

    public function isActivationRequest(): bool
    {
        return (bool) $this->is_activation_request;
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }
}
