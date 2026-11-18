<?php

namespace App\Services\System;

use App\Models\System\MarketplaceListing;

/**
 * Bloquear una tienda desde el SuperAdmin la OCULTA del marketplace; no anula
 * sus productos. Ni los items del tenant ni el estado del listing cambian:
 * solo se apaga `is_active` y `status` se queda en 'active'.
 *
 * Esa combinación (is_active = 0 con status = 'active') no la produce nada
 * más —el sync, al retirar un producto, también pone status = 'paused'—, así
 * que marca exactamente lo ocultado por el bloqueo y al desbloquear se
 * enciende tal cual estaba.
 */
class MarketplaceStoreLock
{
    public static function hide(int $hostnameId): int
    {
        return MarketplaceListing::where('hostname_id', $hostnameId)
            ->where('is_active', true)
            ->where('status', 'active')
            ->update(['is_active' => false]);
    }

    public static function restore(int $hostnameId): int
    {
        return MarketplaceListing::where('hostname_id', $hostnameId)
            ->where('is_active', false)
            ->where('status', 'active')
            ->update(['is_active' => true]);
    }
}
