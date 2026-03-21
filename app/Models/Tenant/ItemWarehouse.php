<?php

namespace App\Models\Tenant;

use App\Enums\StockMovementTypeEnum;
use Illuminate\Support\Facades\Config;

class ItemWarehouse extends ModelTenant
{
    protected $table = 'item_warehouse';

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'stock',
        'stock_physical',
        'stock_committed',
    ];

    protected $appends = ['stock_available'];

    /**
     * Stock disponible para venta (ecommerce y consultas externas).
     * = stock_physical - stock_committed
     */
    public function getStockAvailableAttribute(): float
    {
        return max(0.0, (float)$this->stock_physical - (float)$this->stock_committed);
    }

    /**
     * Verifica si hay suficiente stock disponible para una cantidad dada.
     */
    public function hasAvailableStock(float $qty): bool
    {
        return $this->stock_available >= $qty;
    }

    /**
     * Aplica un movimiento de stock con locking pesimista.
     * Debe llamarse dentro de DB::transaction().
     *
     * @param StockMovementTypeEnum $type
     * @param float $qty   Cantidad (siempre positiva; el enum determina la dirección)
     */
    public function applyStockMovement(StockMovementTypeEnum $type, float $qty): void
    {
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        $this->stock_physical  = max(0, $this->stock_physical  + $physicalDelta);
        $this->stock_committed = max(0, $this->stock_committed + $committedDelta);

        // Mantener retrocompatibilidad: stock = stock_physical
        $this->stock = $this->stock_physical;

        $this->save();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Devuelve la descripcion del almacen
     *
     * @return string
     */
    public function getWarehouseDescription(){
        /** @var Warehouse $warehouse */
        $warehouse = $this->warehouse()->first();
        if(!empty($warehouse)){
            return $warehouse->description;
        }
        return '';
    }

    /**
     * Suma una cantidad $qty al stock. Si la cantidad es negativa lo restará
     *
     * @param float|int $qty
     * @return $this
     */
    public function addStock (float $qty =0 ){
        $this->stock += $qty;
        return $this;
    }
}
