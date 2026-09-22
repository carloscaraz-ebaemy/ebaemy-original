<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Evento de autenticacion del sistema (superadmin). Solo se inserta (nunca se actualiza).
 */
class LoginEvent extends Model
{
    use UsesSystemConnection;

    public const UPDATED_AT = null;

    protected $table = 'login_events';

    protected $fillable = [
        'user_id', 'guard', 'email', 'result', 'ip_address',
        'country_code', 'city', 'latitude', 'longitude',
        'user_agent', 'url', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'latitude'   => 'float',
        'longitude'  => 'float',
    ];

    public function scopeFailed($query)
    {
        return $query->where('result', 'failed');
    }

    public function scopeSuccessful($query)
    {
        return $query->where('result', 'success');
    }

    public function scopeSince($query, $moment)
    {
        return $query->where('created_at', '>=', $moment);
    }
}
