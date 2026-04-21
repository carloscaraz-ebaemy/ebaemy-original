<?php

namespace App\Models\Tenant;

class WhatsAppMessageLog extends ModelTenant
{
    protected $table = 'whatsapp_messages_log';

    protected $fillable = [
        'phone',
        'driver',
        'type',
        'template_name',
        'message',
        'status',
        'source',
        'source_id',
        'error_message',
        'external_id',
        'user_id',
        'meta',
        'sent_at',
    ];

    protected $casts = [
        'meta'    => 'array',
        'sent_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSent($q) { return $q->where('status', 'sent'); }
    public function scopeFailed($q) { return $q->where('status', 'failed'); }
    public function scopeByDriver($q, string $d) { return $q->where('driver', $d); }
    public function scopeBySource($q, string $s, ?int $id = null) {
        $q->where('source', $s);
        if ($id) $q->where('source_id', $id);
        return $q;
    }
}
