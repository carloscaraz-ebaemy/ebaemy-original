<?php

namespace App\Models\Tenant;

class WhatsAppOfferCampaign extends ModelTenant
{
    protected $table = 'whatsapp_offer_campaigns';

    protected $fillable = [
        'name',
        'flash_sale_id',
        'status',
        'total_customers',
        'sent_count',
        'failed_count',
        'meta',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function messages()
    {
        return $this->hasMany(WhatsAppOfferCampaignMessage::class, 'campaign_id');
    }

    public function flashSale()
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }
}

