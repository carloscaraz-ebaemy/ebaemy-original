<?php

namespace App\Models\Tenant;

/**
 * Bitácora de la hoja de reclamación. Sólo se escribe: nada la edita ni la
 * borra, porque es la prueba de qué se hizo y cuándo.
 *
 * @mixin ModelTenant
 */
class ClaimEvent extends ModelTenant
{
    protected $table = 'claim_events';

    public const ACTION_CREATED        = 'created';
    public const ACTION_STATUS_CHANGED = 'status_changed';
    public const ACTION_ANSWERED       = 'answered';
    public const ACTION_MAIL_SENT      = 'mail_sent';
    public const ACTION_MAIL_FAILED    = 'mail_failed';

    protected $fillable = [
        'claim_id', 'action', 'from_status', 'to_status',
        'user_id', 'user_name', 'description', 'payload', 'ip',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }
}
