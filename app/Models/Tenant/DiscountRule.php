<?php

namespace App\Models\Tenant;

/**
 * Regla de descuento automático.
 *
 * Tipos: volume, auto, channel, flash_sale, bundle
 * Se aplican sin código del cliente, a diferencia de Coupon.
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
        'apply_category_id',
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
                     ->where(fn($q) => $q->whereNull('max_uses')->orWhere('max_uses', 0)->orWhereColumn('used_count', '<', 'max_uses'));
    }

    public function scopeForChannel($query, ?int $channelId, ?string $channelType = null)
    {
        return $query->where(function ($q) use ($channelId, $channelType) {
            $q->whereNull('channel_id');
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

    public function applyCategory()
    {
        return $this->belongsTo(\Modules\Item\Models\Category::class, 'apply_category_id');
    }

    public function channel()
    {
        return $this->belongsTo(SalesChannel::class, 'channel_id');
    }

    // ─── Lógica de cálculo ────────────────────────────────────────────────────

    public function calculateDiscount(float $amount): float
    {
        if ($amount <= 0) return 0;

        $discount = $this->discount_type === 'percentage'
            ? round($amount * min($this->discount_value, 100) / 100, 2)
            : (float) $this->discount_value;

        return min(max($discount, 0), $amount);
    }

    /**
     * Calcula el descuento considerando `applies_to`:
     *   all       → sobre el total restante del carrito
     *   item      → solo sobre la suma de líneas del producto seleccionado
     *   bundle    → solo sobre la suma de líneas del pack (is_set) seleccionado
     *   category  → solo sobre las líneas de la categoría seleccionada
     *
     * El resultado nunca excede $remaining (el monto pendiente del carrito) para
     * evitar que dos reglas sumadas dejen un total negativo.
     */
    public function calculateScopedDiscount(array $cart, float $remaining): float
    {
        if ($remaining <= 0) return 0;

        $base = $this->resolveScopedBase($cart, $remaining);
        if ($base <= 0) return 0;

        $discount = $this->discount_type === 'percentage'
            ? round($base * min($this->discount_value, 100) / 100, 2)
            : (float) $this->discount_value;

        return min(max($discount, 0), $remaining);
    }

    /**
     * Monto base (con IGV, tal como lo ve el cliente) al que aplica el descuento.
     * Si no hay matching (ej. el producto target no está en el carrito), retorna 0
     * y la regla queda neutralizada.
     */
    private function resolveScopedBase(array $cart, float $fallback): float
    {
        $scope = $this->applies_to ?: 'all';

        if ($scope === 'all') {
            return $fallback;
        }

        if ($scope === 'item') {
            $targetId = $this->apply_item_id
                ?? ($this->trigger_json['item_id'] ?? null);
            if (!$targetId) return $fallback; // legacy: sin target = todo
            return $this->sumCartLines($cart, fn($item) => (int)($item['id'] ?? 0) === (int)$targetId);
        }

        if ($scope === 'bundle') {
            $targetId = $this->apply_item_id
                ?? ($this->trigger_json['bundle_item_id'] ?? $this->trigger_json['item_id'] ?? null);
            if (!$targetId) return $fallback;
            return $this->sumCartLines(
                $cart,
                fn($item) => ($item['is_set'] ?? false) && (int)($item['id'] ?? 0) === (int)$targetId
            );
        }

        if ($scope === 'category') {
            if (!$this->apply_category_id) return $fallback;
            $itemIds = collect($cart)->map(fn($i) => (int)((array)$i)['id'] ?? 0)->filter()->unique()->values()->all();
            if (empty($itemIds)) return 0;
            $matching = Item::whereIn('id', $itemIds)
                ->where('category_id', $this->apply_category_id)
                ->pluck('id')
                ->all();
            if (empty($matching)) return 0;
            return $this->sumCartLines($cart, fn($item) => in_array((int)($item['id'] ?? 0), $matching, true));
        }

        return $fallback;
    }

    private function sumCartLines(array $cart, callable $predicate): float
    {
        $total = 0;
        foreach ($cart as $raw) {
            $item = (array) $raw;
            if (!$predicate($item)) continue;
            $qty   = (float) ($item['quantity'] ?? $item['cantidad'] ?? 1);
            $price = (float) ($item['sale_unit_price'] ?? $item['unit_price'] ?? $item['price'] ?? 0);
            $total += $qty * $price;
        }
        return round($total, 2);
    }

    /**
     * Verificar si esta regla aplica dado el contexto del carrito.
     */
    public function matches(array $cart, float $subtotal, ?int $channelId = null, ?string $channelType = null): bool
    {
        if (empty($cart) || $subtotal <= 0) return false;

        $trigger = $this->trigger_json ?? [];

        return match ($this->type) {
            'volume'     => $this->matchesVolume($cart, $trigger),
            'auto'       => $this->matchesAuto($subtotal, $trigger),
            'channel'    => $this->matchesChannel($channelId, $channelType, $trigger),
            'flash_sale' => true, // Vigencia controlada por scope active()
            'bundle'     => $this->matchesBundle($cart, $trigger),
            default      => false,
        };
    }

    // ─── Helpers privados ────────────────────────────────────────────────────

    private function matchesVolume(array $cart, array $trigger): bool
    {
        // FIX BUG #1: aceptar ambos nombres de campo
        $minQty   = (int) ($trigger['min_quantity'] ?? $trigger['min_qty'] ?? 2);
        $targetId = $trigger['item_id'] ?? null;

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
