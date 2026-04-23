<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entrada de auditoría inmutable para el workflow de SellerApplication.
 *
 * No tiene `updated_at` — cada cambio de estado, nota o acción del
 * SuperAdmin crea una fila nueva. La historia completa se puede leer
 * ordenando por created_at asc.
 */
class SellerApplicationLog extends Model
{
    use UsesSystemConnection;

    protected $table = 'seller_application_logs';

    public $timestamps = false;

    // ── Acciones registradas ─────────────────────────────────
    public const ACTION_CREATED        = 'created';
    public const ACTION_STATUS_CHANGED = 'status_changed';
    public const ACTION_NOTE_ADDED     = 'note_added';
    public const ACTION_DOCS_REQUESTED = 'docs_requested';
    public const ACTION_APPROVED       = 'approved';
    public const ACTION_REJECTED       = 'rejected';

    protected $fillable = [
        'seller_application_id',
        'action',
        'old_status',
        'new_status',
        'notes',
        'user_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(SellerApplication::class, 'seller_application_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
