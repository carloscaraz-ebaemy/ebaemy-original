<?php

namespace App\Models\Tenant;

class WhatsAppOfferCampaignMessage extends ModelTenant
{
    protected $table = 'whatsapp_offer_campaign_messages';

    protected $fillable = [
        'campaign_id',
        'person_id',
        'phone',
        'status',
        'payload',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(WhatsAppOfferCampaign::class, 'campaign_id');
    }

    public function person()
    {
        return $this->belongsTo(Person::class, 'person_id');
    }
}

