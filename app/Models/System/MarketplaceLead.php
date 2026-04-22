<?php

namespace App\Models\System;

use Hyn\Tenancy\Models\Hostname;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Solicitud de compra generada en el marketplace central. En Fase 2 se envía al
 * tenant correspondiente y se convierte en una Order con channel_id del canal
 * "Marketplace ebaemy".
 */
class MarketplaceLead extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_leads';

    protected $fillable = [
        'listing_id',
        'hostname_id',
        'tenant_fqdn',
        'remote_item_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'quantity',
        'message',
        'snapshot_title',
        'snapshot_price',
        'status',
        'tenant_order_external_id',
        'sync_error',
        'source_ip',
        'source_ua',
        'retry_count',
    ];

    protected $casts = [
        'snapshot_price' => 'float',
        'quantity'       => 'integer',
        'retry_count'    => 'integer',
    ];

    public function listing()
    {
        return $this->belongsTo(MarketplaceListing::class, 'listing_id');
    }

    public function hostname()
    {
        return $this->belongsTo(Hostname::class, 'hostname_id');
    }
}
