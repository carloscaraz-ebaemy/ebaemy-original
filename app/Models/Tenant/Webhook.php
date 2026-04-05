<?php

namespace App\Models\Tenant;

class Webhook extends ModelTenant
{
    protected $fillable = [
        'name', 'url', 'secret', 'events', 'is_active',
        'failure_count', 'last_triggered_at', 'last_failed_at', 'last_error',
    ];

    protected $casts = [
        'events'            => 'array',
        'is_active'         => 'boolean',
        'last_triggered_at' => 'datetime',
        'last_failed_at'    => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function logs()
    {
        return $this->hasMany(WebhookLog::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array($event, $this->events ?? []) || in_array('*', $this->events ?? []);
    }
}
