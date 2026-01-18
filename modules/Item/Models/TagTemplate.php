<?php

namespace Modules\Item\Models;

use App\Models\Tenant\Establishment;
use App\Models\Tenant\ModelTenant;

class TagTemplate extends ModelTenant
{

    public $timestamps = false;
    
    protected $fillable = [
        'name',
        'width',
        'height',
        'establishment_id',
        'is_default'
    ];

    protected $casts = [
        'is_default' => 'boolean'
    ];

    public function establishment()
    {
        return $this->belongsTo(Establishment::class);
    }

    public function fields()
    {
        return $this->hasMany(TagTemplateField::class);
    }
}