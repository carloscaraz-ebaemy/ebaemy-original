<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Campaña de marketing centralizada. Una campaña se compone de targets
 * (uno por contacto) que se procesan según el canal (WhatsApp/Email/SMS).
 */
class MarketingCampaign extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'name', 'channel', 'message', 'subject',
        'status', 'segment',
        'target_count', 'sent_count', 'failed_count',
        'scheduled_at', 'started_at', 'finished_at',
        'created_by',
    ];

    protected $casts = [
        'segment'      => 'array',
        'scheduled_at' => 'datetime',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
        'target_count' => 'integer',
        'sent_count'   => 'integer',
        'failed_count' => 'integer',
    ];

    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_EMAIL    = 'email';
    public const CHANNEL_SMS      = 'sms';

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENDING   = 'sending';
    public const STATUS_SENT      = 'sent';
    public const STATUS_CANCELLED = 'cancelled';

    public function targets()
    {
        return $this->hasMany(MarketingCampaignTarget::class, 'campaign_id');
    }
}
