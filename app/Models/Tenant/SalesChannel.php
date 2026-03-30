<?php

namespace App\Models\Tenant;

/**
 * Canal de venta — abstracción del origen de un pedido.
 *
 * Relaciones:
 *   warehouse()  → Warehouse (almacén por defecto del canal)
 *   orders()     → Order[]   (todos los pedidos generados por este canal)
 *
 * Scopes:
 *   active()     → solo canales activos
 *   ofType($t)   → filtrar por tipo (ecommerce, pos, etc.)
 *
 * Helper estático:
 *   ecommerceChannel() → devuelve (o crea) el canal "ecommerce" del sistema
 */
class SalesChannel extends ModelTenant
{
    protected $table = 'sales_channels';

    protected $fillable = [
        'name',
        'type',
        'code',
        'warehouse_id',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings'  => 'array',
    ];

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function warehouse()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Warehouse::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'channel_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // ─── Helpers estáticos ────────────────────────────────────────────────────

    /**
     * Devuelve el canal ecommerce activo.
     * Si no existe, lo crea con el primer almacén disponible.
     * Útil en paymentCash() para asignar channel_id sin hardcoding.
     */
    public static function ecommerceChannel(): self
    {
        $channel = static::where('type', 'ecommerce')->where('is_active', true)->first();

        if (!$channel) {
            $firstWarehouse = \Modules\Inventory\Models\Warehouse::first();
            $channel = static::create([
                'name'         => 'Tienda Online',
                'type'         => 'ecommerce',
                'code'         => 'ECOM',
                'warehouse_id' => $firstWarehouse?->id,
                'is_active'    => true,
            ]);
        }

        return $channel;
    }

    /**
     * Resumen de ventas de este canal en un rango de fechas.
     *
     * @param string $from  Y-m-d
     * @param string $to    Y-m-d
     */
    public function salesSummary(string $from, string $to): array
    {
        $query = $this->orders()
                      ->whereDate('created_at', '>=', $from)
                      ->whereDate('created_at', '<=', $to)
                      ->whereNotIn('status_order_id', [5]); // excluir cancelados

        return [
            'channel_id'   => $this->id,
            'channel_name' => $this->name,
            'channel_type' => $this->type,
            'order_count'  => $query->count(),
            'revenue'      => (float) $query->sum('total'),
            'avg_ticket'   => (float) $query->avg('total'),
        ];
    }
}
