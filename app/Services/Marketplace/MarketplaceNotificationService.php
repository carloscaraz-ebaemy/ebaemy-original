<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceUser;
use App\Models\System\MarketplaceUserPreference;

/**
 * Centraliza la decision de "puedo mandarle X a este user?".
 *
 * Cumple compliance: consent vigente + preference != off + status active.
 * Cualquier nuevo email/whatsapp del marketplace DEBE pasar por aqui.
 */
class MarketplaceNotificationService
{
    /**
     * @param string $purpose  marketing|price_alerts|abandoned_cart
     */
    public function canSendEmail(MarketplaceUser $user, string $purpose): bool
    {
        if (!$user->isActive()) return false;
        // Transactional NO requiere consent ni preference (legal).
        if ($purpose === 'transactional') return true;

        if (!$user->hasActiveConsent('email', $purpose)) return false;

        $pref = $user->preferences ?? MarketplaceUserPreference::firstOrCreate(
            ['user_id' => $user->id],
            ['email_frequency' => 'weekly', 'whatsapp_frequency' => 'off']
        );
        return $pref->email_frequency !== 'off';
    }

    public function canSendWhatsApp(MarketplaceUser $user, string $purpose): bool
    {
        if (!$user->isActive()) return false;
        if (!$user->phone)      return false;
        if ($purpose === 'transactional') return true;
        if (!$user->hasActiveConsent('whatsapp', $purpose)) return false;
        $pref = $user->preferences;
        return $pref && $pref->whatsapp_frequency !== 'off';
    }
}
