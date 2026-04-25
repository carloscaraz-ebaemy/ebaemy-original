<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Contacto de marketing en BD central. Sin `consent_marketing=true` los
 * servicios de envío deben saltar este contacto.
 */
class MarketingContact extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketing_contacts';

    protected $fillable = [
        'name', 'phone', 'email',
        'consent_marketing', 'consent_at', 'consent_source',
        'opted_out', 'opted_out_at', 'opt_out_reason', 'opt_out_token',
        'tags', 'hostname_id', 'source',
        'last_sent_at', 'sent_count',
    ];

    protected $casts = [
        'consent_marketing' => 'boolean',
        'opted_out'         => 'boolean',
        'consent_at'        => 'datetime',
        'opted_out_at'      => 'datetime',
        'last_sent_at'      => 'datetime',
        'tags'              => 'array',
        'sent_count'        => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $contact) {
            if (empty($contact->opt_out_token)) {
                $contact->opt_out_token = self::generateOptOutToken();
            }
            if ($contact->consent_marketing && !$contact->consent_at) {
                $contact->consent_at = now();
            }
        });
    }

    public static function generateOptOutToken(): string
    {
        do {
            $token = Str::random(48);
        } while (self::where('opt_out_token', $token)->exists());
        return $token;
    }

    /**
     * Puede recibir marketing: tiene consentimiento y no optó por salir.
     */
    public function canReceiveMarketing(string $channel = 'whatsapp'): bool
    {
        if (!$this->consent_marketing || $this->opted_out) {
            return false;
        }
        return match ($channel) {
            'whatsapp', 'sms' => !empty($this->phone),
            'email'           => !empty($this->email),
            default           => false,
        };
    }

    public function scopeReachable($query)
    {
        return $query
            ->where('consent_marketing', true)
            ->where('opted_out', false);
    }
}
