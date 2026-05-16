<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceUser;
use Illuminate\Support\Facades\DB;

/**
 * Merge silencioso de datos anonimos (cookie de sesion) hacia la
 * cuenta del comprador al hacer login.
 *
 * Se invoca DESPUES de Auth::login() pero ANTES de session()->regenerate(),
 * para preservar el session_id usado mientras era anonimo.
 *
 * Reglas:
 *  - Nunca pisar datos del user con datos anonimos (el user tiene prioridad).
 *  - Nunca duplicar: si el listing ya esta favoriteado por el user, dejar
 *    como esta y borrar el del anonimo.
 *  - Idempotente: correrlo dos veces no rompe nada.
 *
 * En Fase 3 se agrega merge de marketplace_user_views (cuando exista).
 */
class MarketplaceUserMergeService
{
    /**
     * @param MarketplaceUser $user        El user recien autenticado.
     * @param string|null     $anonymousSessionId  El session_id de la sesion anonima.
     * @return array{favorites_merged:int, favorites_skipped:int}
     */
    public function mergeFromSession(MarketplaceUser $user, ?string $anonymousSessionId): array
    {
        if (!$anonymousSessionId) {
            return ['favorites_merged' => 0, 'favorites_skipped' => 0];
        }

        // Favoritos del anonimo (filtramos solo los huerfanos sin user_id).
        $anonFavorites = DB::connection('system')->table('marketplace_favorites')
            ->where('session_id', $anonymousSessionId)
            ->whereNull('user_id')
            ->get(['id', 'listing_id']);

        if ($anonFavorites->isEmpty()) {
            return ['favorites_merged' => 0, 'favorites_skipped' => 0];
        }

        // Los que YA tiene el user — para no duplicar.
        $userListingIds = DB::connection('system')->table('marketplace_favorites')
            ->where('user_id', $user->id)
            ->pluck('listing_id')
            ->all();
        $userSet = array_flip(array_map('intval', $userListingIds));

        $toClaim = [];
        $toDelete = [];
        foreach ($anonFavorites as $fav) {
            if (isset($userSet[(int) $fav->listing_id])) {
                // Duplicado: el user ya lo tiene. Borramos el anonimo.
                $toDelete[] = $fav->id;
            } else {
                // Promovemos: cambiamos user_id, dejamos session_id por trazabilidad.
                $toClaim[] = $fav->id;
                $userSet[(int) $fav->listing_id] = true;
            }
        }

        DB::connection('system')->transaction(function () use ($toClaim, $toDelete, $user) {
            if (!empty($toClaim)) {
                DB::connection('system')->table('marketplace_favorites')
                    ->whereIn('id', $toClaim)
                    ->update(['user_id' => $user->id, 'updated_at' => now()]);
            }
            if (!empty($toDelete)) {
                DB::connection('system')->table('marketplace_favorites')
                    ->whereIn('id', $toDelete)
                    ->delete();
            }
        });

        return [
            'favorites_merged'  => count($toClaim),
            'favorites_skipped' => count($toDelete),
        ];
    }
}
