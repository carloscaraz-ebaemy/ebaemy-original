<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Item;
use App\Services\FeatureGate;

/**
 * Cuota de productos publicables en el marketplace central según el plan
 * del tenant. Usa el feature `marketplace_products_limit`:
 *
 *   limit() → 0    : el plan no incluye marketplace (no puede publicar)
 *            null  : ilimitado
 *            int   : máximo permitido
 */
class MarketplaceQuotaService
{
    public function __construct(private readonly FeatureGate $features) {}

    /**
     * Cuántos productos del tenant están publicados ahora mismo.
     * Permite excluir un item específico (útil al re-evaluar un toggle).
     */
    public function currentCount(?int $excludeItemId = null): int
    {
        $q = Item::where('marketplace_publishable', true)
            ->where('mp_status', 'active');

        if ($excludeItemId !== null) {
            $q->where('id', '!=', $excludeItemId);
        }

        return $q->count();
    }

    /**
     * Límite del plan activo. null = ilimitado, 0 = no incluido.
     */
    public function limit(): ?int
    {
        return $this->features->limit('marketplace_products_limit');
    }

    /**
     * Verifica si el tenant puede publicar `extra` productos adicionales.
     *
     * @return array{allowed: bool, reason: ?string, limit: ?int, current: int, remaining: ?int}
     */
    public function canPublish(int $extra = 1, ?int $excludeItemId = null): array
    {
        $limit = $this->limit();

        if ($limit === 0) {
            return [
                'allowed'   => false,
                'reason'    => 'Tu plan no incluye publicación en el marketplace. Actualiza tu plan para activarlo.',
                'limit'     => 0,
                'current'   => 0,
                'remaining' => 0,
            ];
        }

        if ($limit === null) {
            return [
                'allowed'   => true,
                'reason'    => null,
                'limit'     => null,
                'current'   => $this->currentCount($excludeItemId),
                'remaining' => null,
            ];
        }

        $current = $this->currentCount($excludeItemId);
        $remaining = max(0, $limit - $current);

        if ($current + $extra > $limit) {
            return [
                'allowed'   => false,
                'reason'    => "Has llegado al límite de tu plan ({$limit} productos publicados en marketplace). Actualiza tu plan para publicar más.",
                'limit'     => $limit,
                'current'   => $current,
                'remaining' => $remaining,
            ];
        }

        return [
            'allowed'   => true,
            'reason'    => null,
            'limit'     => $limit,
            'current'   => $current,
            'remaining' => $remaining,
        ];
    }
}
