<?php

namespace Modules\Restaurant\Models;

use App\Models\Tenant\ModelTenant;
use App\Models\Tenant\Catalogs\UnitType;

/**
 * Modelo para gestión de insumos del restaurante
 *
 * Gestiona el inventario de insumos/materias primas utilizadas en la preparación de productos
 *
 * @property int $id
 * @property string $name
 * @property float $cost
 * @property int $unit_type_id
 * @property float $waste_percentage
 * @property float $stock
 * @property float $minimum_stock
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Supply extends ModelTenant
{
    protected $table = 'supplies';

    protected $fillable = [
        'name',
        'cost',
        'unit_type_id',
        'waste_percentage',
        'stock',
        'minimum_stock',
    ];

    protected $casts = [
        'cost' => 'decimal:2',
        'waste_percentage' => 'decimal:2',
        'stock' => 'decimal:4',
        'minimum_stock' => 'decimal:4',
    ];

    /**
     * Relación con el tipo de unidad (kg, litros, unidades, etc.)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function unitType()
    {
        return $this->belongsTo(UnitType::class, 'unit_type_id');
    }

    /**
     * Verifica si el stock está por debajo del mínimo
     *
     * @return bool
     */
    public function isBelowMinimumStock()
    {
        return $this->stock < $this->minimum_stock;
    }

    /**
     * Calcula el costo efectivo considerando la merma
     *
     * @return float
     */
    public function getEffectiveCost()
    {
        $wasteMultiplier = 1 + ($this->waste_percentage / 100);
        return $this->cost * $wasteMultiplier;
    }
}
