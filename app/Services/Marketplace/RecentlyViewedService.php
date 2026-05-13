<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceListing;
use Illuminate\Support\Collection;

/**
 * Tracking session-based de productos recientemente vistos por el visitante.
 *
 * Implementación 100% en cookie de sesión Laravel — sin tabla. Si más
 * adelante queremos persistir cross-device, mover a una tabla similar
 * a marketplace_favorites con session_id + user_id.
 *
 * Limita a MAX_ITEMS para no inflar la sesión y para que sea siempre
 * "recientes" (no historial completo).
 */
class RecentlyViewedService
{
    public const SESSION_KEY = 'mp_recent_viewed';
    public const MAX_ITEMS   = 12;

    /**
     * Registra un listing como recién visto. Si ya estaba, lo sube al tope
     * (LRU). El más reciente queda en posición 0.
     */
    public function push(int $listingId): void
    {
        if ($listingId <= 0) return;

        $ids = session(self::SESSION_KEY, []);
        if (!is_array($ids)) $ids = [];

        // Quitar duplicado si ya estaba
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== $listingId));

        // Insertar al principio
        array_unshift($ids, $listingId);

        // Cap
        if (count($ids) > self::MAX_ITEMS) {
            $ids = array_slice($ids, 0, self::MAX_ITEMS);
        }

        session([self::SESSION_KEY => $ids]);
    }

    /**
     * Devuelve los IDs guardados (más reciente primero), opcionalmente
     * excluyendo el que se está viendo ahora (para no incluirse a sí mismo
     * en el carousel del detail page).
     */
    public function ids(?int $excludeId = null): array
    {
        $ids = session(self::SESSION_KEY, []);
        if (!is_array($ids)) return [];

        if ($excludeId !== null) {
            $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== $excludeId));
        }

        return array_map('intval', $ids);
    }

    /**
     * Devuelve los MarketplaceListing publicados correspondientes al
     * recently-viewed, en el ORDEN del LRU (más reciente primero). Si
     * alguno ya no está publicado (retirado / sin stock), se omite — sin
     * romper la lista.
     */
    public function listings(?int $excludeId = null, int $limit = 8): Collection
    {
        $ids = $this->ids($excludeId);
        if (empty($ids)) return collect();

        $ids = array_slice($ids, 0, $limit);

        $listings = MarketplaceListing::published()
            ->whereIn('id', $ids)
            ->get();

        // Reordenar para respetar el orden del LRU.
        $position = array_flip($ids);
        return $listings->sortBy(fn ($l) => $position[$l->id] ?? PHP_INT_MAX)->values();
    }
}
