<?php

namespace App\Models\System;

use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

/**
 * Oficina de Shalom, copiada del API por ShalomAgencyCatalog::sync().
 *
 * La clave primaria es el `ter_id` de Shalom: es el id que piden la
 * cotización y la creación de guías, y el que guarda el envío en
 * `shipping_requests.agency_office_id`.
 */
class ShalomAgency extends Model
{
    use UsesSystemConnection;

    protected $table = 'shalom_agencies';
    protected $primaryKey = 'ter_id';
    public $incrementing = false;

    protected $fillable = [
        'ter_id', 'name', 'full_name', 'department', 'province', 'zone', 'ubigeo',
        'address', 'phone', 'hours', 'details', 'latitude', 'longitude',
        'receives', 'home_delivery', 'listed', 'missing_since', 'raw',
    ];

    protected $casts = [
        'receives'      => 'boolean',
        'home_delivery' => 'boolean',
        'listed'        => 'boolean',
        'missing_since' => 'datetime',
        'raw'           => 'array',
    ];

    /** Las que el cliente puede elegir: siguen en el API y reciben envíos. */
    public function scopeSelectable($q)
    {
        return $q->where('listed', true)->where('receives', true);
    }
}
