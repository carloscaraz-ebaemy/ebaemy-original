<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class MarketplaceUserPreference extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_user_preferences';
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id', 'email_frequency', 'whatsapp_frequency', 'categories_subscribed',
    ];

    protected $casts = [
        'categories_subscribed' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(MarketplaceUser::class, 'user_id');
    }
}
