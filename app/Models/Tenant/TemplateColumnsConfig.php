<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Hyn\Tenancy\Traits\UsesTenantConnection;

class TemplateColumnsConfig extends ModelTenant
{
    use UsesTenantConnection;

    protected $table = 'template_columns_config';

    protected $fillable = [
        'establishment_id',
        'template_name',
        'columns_config',
    ];

    protected $casts = [
        'columns_config' => 'array',
    ];

    /**
     * Relación con Establishment
     */
    public function establishment()
    {
        return $this->belongsTo(Establishment::class);
    }

    /**
     * Obtener configuración o crear una por defecto
     */
    public static function getOrCreateConfig($establishmentId, $templateName = 'Plantilla_personalizable')
    {
        return self::firstOrCreate(
            [
                'establishment_id' => $establishmentId,
                'template_name' => $templateName,
            ],
            [
                'columns_config' => [
                    'codigo' => true,
                    'descripcion' => true,
                    'cantidad' => true,
                    'unidad' => true,
                    'serie' => false,
                    'modelo' => false,
                    'marca' => false,
                    'lote' => false,
                    'fecha_vencimiento' => false,
                    'precio_unitario' => true,
                    'descuento' => true,
                    'total' => true,
                ]
            ]
        );
    }
}
