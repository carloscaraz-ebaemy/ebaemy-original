<?php

namespace App\Services\Marketplace;

use App\Models\System\MarketplaceUser;
use App\Services\System\WhatsAppSystemService;

/**
 * Envia WhatsApp al comprador del marketplace via WhatsAppSystemService.
 *
 * SIEMPRE valida consent + preferences (MarketplaceNotificationService).
 * NO requiere contexto de tenant — usa la config SaaS de system.
 *
 * Plantillas Meta requeridas (para registro en Meta Business Manager):
 *  - mkt_price_drop_v1 (price_alerts)
 *  - mkt_weekly_offers_v1 (marketing)
 *  - mkt_abandoned_cart_v1 (abandoned_cart)
 *
 * Hasta que esten aprobadas, los mensajes van como texto libre (solo
 * funciona dentro de la ventana 24h de Meta o con instancias QR API).
 */
class MarketplaceWhatsAppNotifier
{
    public function __construct(
        private MarketplaceNotificationService $gate,
        private WhatsAppSystemService $wa,
    ) {}

    public function sendPriceDrop(MarketplaceUser $user, array $drops): bool
    {
        if (!$this->gate->canSendWhatsApp($user, 'price_alerts')) return false;
        $count = count($drops);
        if ($count === 0) return false;
        $first = $drops[0];
        $msg = "Hola {$user->name}!\n\n";
        if ($count === 1) {
            $pct = $first['saving_pct'] ?? 0;
            $msg .= "📉 Un producto que guardaste bajo de precio:\n";
            $msg .= "*{$first['title']}*\n";
            $msg .= "Ahora S/ " . number_format($first['new_price'], 2) . " (antes S/ " . number_format($first['old_price'], 2) . ") -{$pct}%\n";
            $msg .= "\n" . url('/marketplace/item/' . $first['slug']);
        } else {
            $msg .= "📉 {$count} productos que guardaste bajaron de precio:\n\n";
            foreach (array_slice($drops, 0, 5) as $d) {
                $msg .= "• {$d['title']} → S/ " . number_format($d['new_price'], 2) . " (-{$d['saving_pct']}%)\n";
            }
            $msg .= "\nVer todos: " . url('/marketplace/account');
        }
        return $this->wa->send(
            $user->phone,
            $msg,
            null,
            $user->name,
            'marketplace_price_drop'
        );
    }

    public function sendAbandonedCart(MarketplaceUser $user, int $itemsCount, ?string $couponCode = null): bool
    {
        if (!$this->gate->canSendWhatsApp($user, 'abandoned_cart')) return false;
        $msg = "Hola {$user->name}!\n\n";
        $msg .= "🛒 Dejaste {$itemsCount} producto" . ($itemsCount === 1 ? '' : 's') . " en tu carrito de ebaemy.\n";
        if ($couponCode) {
            $msg .= "\nUsa el codigo *{$couponCode}* en tu compra para un descuento extra.\n";
        }
        $msg .= "\nRetomar: " . url('/marketplace/cart');
        return $this->wa->send(
            $user->phone,
            $msg,
            null,
            $user->name,
            'marketplace_abandoned_cart'
        );
    }

    public function sendWeeklyOffers(MarketplaceUser $user, array $offers, array $categoryNames): bool
    {
        if (!$this->gate->canSendWhatsApp($user, 'marketing')) return false;
        if (empty($offers)) return false;
        $catList = !empty($categoryNames) ? implode(', ', array_slice($categoryNames, 0, 2)) : '';
        $msg = "Hola {$user->name}!\n\n";
        $msg .= "🔥 Ofertas de la semana en {$catList}:\n\n";
        foreach (array_slice($offers, 0, 5) as $o) {
            $msg .= "• {$o['title']} → S/ " . number_format($o['price'], 2);
            if (!empty($o['discount_pct'])) $msg .= " (-{$o['discount_pct']}%)";
            $msg .= "\n";
        }
        $msg .= "\nMas en " . url('/marketplace');
        return $this->wa->send(
            $user->phone,
            $msg,
            null,
            $user->name,
            'marketplace_weekly_offers'
        );
    }
}
