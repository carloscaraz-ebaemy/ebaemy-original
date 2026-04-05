<?php

namespace App\Models\Tenant;

class WebhookLog extends ModelTenant
{
    public $timestamps = false;

    protected $fillable = [
        'webhook_id', 'event', 'payload', 'response_status',
        'response_body', 'duration_ms', 'success', 'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'success'    => 'boolean',
        'created_at' => 'datetime',
    ];

    public function webhook()
    {
        return $this->belongsTo(Webhook::class);
    }
}
