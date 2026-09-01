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
     * Canal de los encargos que entran por «Registro y Control de Envíos».
     *
     * No es una tienda: es la puerta por la que entra trabajo logístico sin
     * venta detrás («lleva este paquete a esta persona»). Tiene canal propio
     * —y de tipo `other`, no `ecommerce`— para que se pueda filtrar en el panel
     * y para que no se cuele en las métricas de venta como si fueran pedidos de
     * una tienda: sus importes son cero por definición.
     */
    public static function shipmentChannel(): self
    {
        $canal = static::where('code', 'ENV01')->first();

        if (!$canal) {
            $canal = static::create([
                'name'         => 'Registro de Envíos',
                'type'         => 'other',
                'code'         => 'ENV01',
                'warehouse_id' => \Modules\Inventory\Models\Warehouse::value('id'),
                'is_active'    => true,
                'settings'     => ['icon' => '📦', 'color' => '#0f766e'],
            ]);
        }

        return $canal;
    }

    /**
     * Canal de venta de un marketplace externo (Saga, MercadoLibre…), creándolo
     * si el tenant todavía no lo tiene.
     *
     * Sustituye al `where('name','LIKE','%'.$platform.'%')` que usaba
     * `MarketplaceOrder::createErpOrder()`: las migraciones solo siembran
     * «Marketplace ebaemy» (MKP01), ningún nombre contiene «falabella», y la
     * búsqueda no encontraba nada nunca. El resultado eran pedidos de Saga con
     * `channel_id` NULL — invisibles para el filtro de canal y para el reporte
     * de ventas por canal. En producción eran los 630 de carolayimport.
     *
     * El código se deriva de la plataforma (`MKP_FALABELLA`) para que una
     * integración nueva no necesite migración: se autoprovisiona al primer
     * pedido.
     *
     * Se crea ACTIVO. Parecía más prudente lo contrario, pero `is_active` no
     * significa aquí «ofrecer en el alta manual»: es lo que filtran
     * `OrderController::channels()` y `channelReport()`. Un canal inactivo con
     * pedidos dentro no se puede elegir en el filtro del panel ni aparece en el
     * reporte de ventas por canal — exactamente el problema que este método
     * viene a resolver. Si el canal existe es porque acaba de llegar un pedido
     * suyo: está en uso por definición.
     */
    /**
     * Código de canal para una plataforma externa: `falabella` → `MKP_FALABELLA`.
     *
     * Vive aparte y sin tocar la base de datos porque la migración de backfill
     * necesita EXACTAMENTE el mismo código: si las dos implementaciones
     * divergen, el backfill crea un canal y el alta en vivo crea otro, y las
     * ventas de la misma tienda quedan partidas en dos en el reporte.
     */
    public static function platformCode(string $platform): string
    {
        return substr(
            'MKP_' . strtoupper(preg_replace('/[^a-z0-9]/', '', strtolower(trim($platform)))),
            0,
            20
        );
    }

    public static function marketplacePlatformChannel(?string $platform, ?string $nombre = null): ?self
    {
        $platform = strtolower(trim((string) $platform));
        if ($platform === '') {
            return null;
        }

        $code = static::platformCode($platform);

        if ($canal = static::where('code', $code)->first()) {
            return $canal;
        }

        // Compatibilidad: si alguien ya lo creó a mano con otro código pero un
        // nombre reconocible, se reutiliza en vez de duplicar el canal.
        $canal = static::where('type', 'marketplace')
                       ->where('name', 'LIKE', '%' . $platform . '%')
                       ->first();
        if ($canal) {
            return $canal;
        }

        return static::create([
            'name'         => substr($nombre ?: ucfirst($platform), 0, 60),
            'type'         => 'marketplace',
            'code'         => $code,
            'warehouse_id' => \Modules\Inventory\Models\Warehouse::value('id'),
            'is_active'    => true,
            'settings'     => ['icon' => '🛍️', 'color' => '#0ea5e9', 'platform' => $platform],
        ]);
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
