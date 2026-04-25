<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class MarketingCampaignTarget extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketing_campaign_targets';

    protected $fillable = [
        'campaign_id', 'contact_id',
        'status', 'error', 'sent_at', 'skip_reason',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function contact()
    {
        return $this->belongsTo(MarketingContact::class, 'contact_id');
    }
}
