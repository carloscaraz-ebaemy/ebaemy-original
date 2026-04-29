<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class SystemWhatsAppLog extends Model
{
    use UsesSystemConnection;

    protected $table = 'system_whatsapp_logs';

    protected $fillable = [
        'tenant_hostname_id',
        'recipient_phone',
        'recipient_name',
        'message',
        'status',
        'source',
        'error_message',
        'admin_user_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
