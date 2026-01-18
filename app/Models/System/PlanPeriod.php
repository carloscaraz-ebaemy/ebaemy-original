<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class PlanPeriod extends Model
{
    use UsesSystemConnection;


    public function client()
    {
        return $this->hasMany(Client::class, 'plan_period_id');
    }
    
}
