<?php

namespace App\Models\Tenant;

use App\Enums\StockMovementTypeEnum;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

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

    protected $casts = [
        'stock' => 'float',
        'item_id' => 'int',
        'warehouse_id' => 'int',
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

        if ($this->stock_physical + $physicalDelta < 0) {
            Log::warning('Stock would go negative', [
                'item_id' => $this->item_id,
                'warehouse_id' => $this->warehouse_id,
                'current_physical' => $this->stock_physical,
                'delta' => $physicalDelta,
                'type' => $type->value,
            ]);
        }

        if ($this->stock_committed + $committedDelta < 0) {
            Log::warning('Stock committed would go negative', [
                'item_id' => $this->item_id,
                'warehouse_id' => $this->warehouse_id,
                'current_committed' => $this->stock_committed,
                'delta' => $committedDelta,
                'type' => $type->value,
            ]);
        }

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
     * Suma una cantidad $qty al stock. Si la cantidad es negativa lo restará.
     * Mantiene `stock` y `stock_physical` sincronizados para no desalinear
     * el sistema legacy (facturación/reportes) con el nuevo (ecommerce/despacho).
     *
     * @param float|int $qty
     * @return $this
     */
    public function addStock (float $qty =0 ){
        $this->stock = max(0, (float) $this->stock + $qty);
        $this->stock_physical = max(0, (float) ($this->stock_physical ?? $this->stock) + $qty);
        return $this;
    }
}
