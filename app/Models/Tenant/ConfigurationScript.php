<?php

namespace App\Models\Tenant;


class ConfigurationScript extends ModelTenant
{
    protected $table = 'configuration_pixels';

    protected $fillable = [
        'title',
        'script',
        'position',
        'active',
    ];
}