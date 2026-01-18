<?php

namespace Modules\Restaurant\Models;

use App\Models\Tenant\ModelTenant;

class ModifierGroup extends ModelTenant
{
    protected $table = 'modifier_groups';

    protected $fillable = [
        'name',
        'selection_type',
        'items',
        'active',
    ];

    protected $casts = [
        'items' => 'array',
        'active' => 'boolean',
    ];
}
