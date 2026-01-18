<?php

namespace Modules\Restaurant\Models;

use App\Models\Tenant\ModelTenant;

class RestaurantTableEnv extends ModelTenant
{
    const ENV_1 =  'Ambiente 1';
    const ENV_2 =  'Ambiente 2';
    const ENV_3 =  'Ambiente 3';
    const ENV_4 =  'Ambiente 4';

    public $timestamps = false;

    public $incrementing = true;
    public $keyType = 'int';

    protected $fillable = [
        'name',
        'active',
        'tables_quantity',
        'is_delivery',
        'is_takeaway',
        'can_edit',
        'can_deactivate',
        'can_delete',
    ];

    protected $casts = [
        'active' => 'boolean',
        'is_delivery' => 'boolean',
        'is_takeaway' => 'boolean',
        'can_edit' => 'boolean',
        'can_deactivate' => 'boolean',
        'can_delete' => 'boolean',
    ];
}
