<?php

namespace Modules\Purchase\Models;

use App\Models\Tenant\ModelTenant;
use Modules\Restaurant\Models\Supply;

class PurchaseSupply extends ModelTenant
{
    protected $fillable = [
        'purchase_id',
        'supply_id',
        'quantity',
        'cost',
        'affectation_igv_type_id',
    ];

    /**
     * Relación con Purchase
     */
    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Relación con Supply
     */
    public function supply()
    {
        return $this->belongsTo(Supply::class, 'supply_id');
    }

    /**
     * Calcula la cantidad efectiva considerando el porcentaje de merma
     *
     * @return float
     */
    public function getEffectiveQuantity()
    {
        $supply = $this->supply;
        if (!$supply) {
            return $this->quantity;
        }

        $wastePercentage = $supply->waste_percentage ?? 0;
        $effectiveQuantity = $this->quantity * (1 - ($wastePercentage / 100));

        return round($effectiveQuantity, 2);
    }
}
