<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogisticOrderItem extends ModelTenant
{
    protected $table = 'logistic_order_items';

    protected $fillable = [
        'logistic_order_id',
        'item_id',
        'warehouse_id',
        'description',
        'unit_type_id',
        'quantity',
        'unit_price',
        'unit_price_with_igv',
        'affectation_igv_type_id',
        'total_base_igv',
        'total_igv',
        'total',
    ];

    protected $casts = [
        'quantity'            => 'float',
        'unit_price'          => 'float',
        'unit_price_with_igv' => 'float',
        'total_base_igv'      => 'float',
        'total_igv'           => 'float',
        'total'               => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LogisticOrder::class, 'logistic_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Devuelve el ItemWarehouse correspondiente a este ítem+almacén.
     */
    public function getItemWarehouse(): ?ItemWarehouse
    {
        return ItemWarehouse::where('item_id', $this->item_id)
                            ->where('warehouse_id', $this->warehouse_id)
                            ->first();
    }

    /**
     * Calcula los totales con IGV basándose en la tasa del tipo de afectación.
     * Afectación 10 (Gravado): aplica IGV 18%.
     * Otras: sin IGV.
     */
    public function calculateTotals(float $igvRate = 0.18): void
    {
        $isGravado = $this->affectation_igv_type_id === '10';

        if ($isGravado) {
            $this->total_base_igv = round($this->quantity * $this->unit_price, 4);
            $this->total_igv      = round($this->total_base_igv * $igvRate, 4);
            $this->total          = $this->total_base_igv + $this->total_igv;
            $this->unit_price_with_igv = round($this->unit_price * (1 + $igvRate), 4);
        } else {
            $this->total_base_igv      = 0;
            $this->total_igv           = 0;
            $this->total               = round($this->quantity * $this->unit_price, 4);
            $this->unit_price_with_igv = $this->unit_price;
        }
    }
}
