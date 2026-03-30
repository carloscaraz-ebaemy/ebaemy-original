<?php

namespace App\Models\Tenant;

/**
 * Regla de descuento automático.
 *
 * Diseño: un solo modelo que soporta todos los tipos de descuento automático,
 * con un campo JSON flexible para las condiciones de activación.
 *
 * Diferencia con Coupon: las DiscountRules se aplican sin código del cliente.
 */
class DiscountRule extends ModelTenant
{
    protected $table = 'discount_rules';

    protected $fillable = [
        'name',
        'type',
        'trigger_json',
        'discount_type',
        'discount_value',
        'applies_to',
        'apply_item_id',
        'channel_id',
        'max_uses',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
        'stackable',
    ];

    protected $casts = [
        'trigger_json'   => 'array',
        'discount_value' => 'float',
        'is_active'      => 'boolean',
        'stackable'      => 'boolean',
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
    ];

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                     ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                     ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
                     ->where(fn($q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));
    }

    public function scopeForChannel($query, ?int $channelId, ?string $channelType = null)
    {
        return $query->where(function ($q) use ($channelId, $channelType) {
            $q->whereNull('channel_id'); // aplica a todos los canales
            if ($channelId) {
                $q->orWhere('channel_id', $channelId);
            }
            if ($channelType) {
                $q->orWhereHas('channel', fn($cq) => $cq->where('type', $channelType));
            }
        });
    }

    public function scopeByPriority($query)
    {
        return $query->orderByDesc('priority');
    }

    // ─── Relaciones ───────────────────────────────────────────────────────────

    public function applyItem()
    {
        return $this->belongsTo(Item::class, 'apply_item_id');
    }

    public function channel()
    {
        return $this->belongsTo(SalesChannel::class, 'channel_id');
    }

    // ─── Lógica de cálculo ────────────────────────────────────────────────────

    /**
     * Calcular el descuento monetario que aplica esta regla.
     *
     * @param float $amount  Monto sobre el cual calcular
     * @return float
     */
    public function calculateDiscount(float $amount): float
    {
        $discount = $this->discount_type === 'percentage'
            ? round($amount * $this->discount_value / 100, 2)
            : (float) $this->discount_value;

        return min($discount, $amount);
    }

    /**
     * Verificar si esta regla aplica dado el contexto del carrito.
     *
     * @param array $cart      Array de items: [{id, quantity, sale_unit_price, is_set, ...}]
     * @param float $subtotal  Total del carrito antes de descuentos
     * @param int|null $channelId
     * @param string|null $channelType
     * @return bool
     */
    public function matches(array $cart, float $subtotal, ?int $channelId = null, ?string $channelType = null): bool
    {
        $trigger = $this->trigger_json ?? [];

        return match ($this->type) {
            'volume'     => $this->matchesVolume($cart, $trigger),
            'auto'       => $this->matchesAuto($subtotal, $trigger),
            'channel'    => $this->matchesChannel($channelId, $channelType, $trigger),
            'flash_sale' => true, // Vigencia ya controlada por scope active()
            'bundle'     => $this->matchesBundle($cart, $trigger),
            default      => false,
        };
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    private function matchesVolume(array $cart, array $trigger): bool
    {
        $minQty    = (int) ($trigger['min_qty'] ?? 2);
        $targetId  = $trigger['item_id'] ?? null; // null = cualquier producto

        foreach ($cart as $item) {
            $item = (array) $item;
            $qty  = (int) ($item['quantity'] ?? $item['cantidad'] ?? 1);

            if ($targetId && (int)($item['id'] ?? 0) !== (int)$targetId) {
                continue;
            }

            if ($qty >= $minQty) {
                return true;
            }
        }

        return false;
    }

    private function matchesAuto(float $subtotal, array $trigger): bool
    {
        $minAmount = (float) ($trigger['min_amount'] ?? 0);
        return $subtotal >= $minAmount;
    }

    private function matchesChannel(?int $channelId, ?string $channelType, array $trigger): bool
    {
        $requiredType = $trigger['channel_type'] ?? null;
        $requiredId   = isset($trigger['channel_id']) ? (int) $trigger['channel_id'] : null;

        if ($requiredId && $channelId !== $requiredId) {
            return false;
        }
        if ($requiredType && $channelType !== $requiredType) {
            return false;
        }

        return true;
    }

    private function matchesBundle(array $cart, array $trigger): bool
    {
        $bundleItemId = $trigger['bundle_item_id'] ?? $this->apply_item_id;
        if (!$bundleItemId) {
            return false;
        }

        foreach ($cart as $item) {
            $item = (array) $item;
            if (($item['is_set'] ?? false) && (int)($item['id'] ?? 0) === (int)$bundleItemId) {
                return true;
            }
        }

        return false;
    }
}
