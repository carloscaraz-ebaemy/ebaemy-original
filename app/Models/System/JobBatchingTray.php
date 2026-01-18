<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class JobBatchingTray extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'job_batch_id',
        'generated_filename'
    ];


}