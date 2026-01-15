<?php

namespace Modules\Dispatch\Models;

use App\Models\Tenant\ModelTenant;

class Transport extends ModelTenant
{
    protected $fillable = [
        'model',
        'brand',
        'plate_number',
        'is_default',
        'is_active',
        'tuc'
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Mutador para plate_number - Asegura que siempre sea alfanumérico y mayúsculas
     */
    public function setPlateNumberAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['plate_number'] = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value));
        } else {
            $this->attributes['plate_number'] = null;
        }
    }

    /**
     * Mutador para tuc - Asegura que siempre sea alfanumérico y mayúsculas
     */
    public function setTucAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['tuc'] = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $value));
        } else {
            $this->attributes['tuc'] = null;
        }
    }
}
