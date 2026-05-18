<?php

namespace App\Mail\Marketplace;

use App\Models\System\MarketplaceUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Mail recordatorio para usuarios con cupones que vencen en <48h.
 * Item 9 del roadmap de visibilidad  alternativa al carrito abandonado
 * (que requerira persistencia BD de carrito, hoy en sesin) pero con
 * el mismo objetivo de traer al user de vuelta.
 *
 * Dispatched desde el comando artisan
 * `php artisan coupons:expiring-reminder`.
 */
class CouponExpiringReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public MarketplaceUser $user;
    public Collection $coupons;

    /**
     * @param Collection $coupons items con keys: code, name, type, value, expires_at
     */
    public function __construct(MarketplaceUser $user, Collection $coupons)
    {
        $this->user = $user;
        $this->coupons = $coupons;
    }

    public function build()
    {
        $subject = $this->coupons->count() === 1
            ? '' . $this->coupons->first()->code . ' vence pronto - sala antes!'
            : 'Tienes ' . $this->coupons->count() . ' cupones por vencer';
        return $this->subject($subject)
                    ->view('emails.marketplace.coupon_expiring_reminder');
    }
}
