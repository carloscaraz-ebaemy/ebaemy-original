<?php

namespace App\Models\Tenant;

class ScheduledReport extends ModelTenant
{
    protected $fillable = [
        'name', 'report_type', 'frequency', 'send_to',
        'send_time', 'send_day', 'is_active',
        'last_sent_at', 'last_error',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Verificar si debe enviarse ahora.
     */
    public function shouldSendNow(): bool
    {
        $now = now();

        if ($now->format('H:i') !== $this->send_time) {
            return false;
        }

        // Si ya se envió hoy, no repetir
        if ($this->last_sent_at && $this->last_sent_at->isToday()) {
            return false;
        }

        return match ($this->frequency) {
            'daily'   => true,
            'weekly'  => $now->dayOfWeekIso === ($this->send_day ?? 1), // 1=Lunes
            'monthly' => $now->day === ($this->send_day ?? 1),
            default   => false,
        };
    }

    public function getRecipients(): array
    {
        return array_filter(array_map('trim', explode(',', $this->send_to)));
    }
}
