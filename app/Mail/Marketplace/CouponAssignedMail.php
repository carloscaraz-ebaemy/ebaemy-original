<?php

namespace App\Mail\Marketplace;

use App\Models\System\MarketplaceCoupon;
use App\Models\System\MarketplaceUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Mail enviado al comprador cuando se le asigna un cupn nuevo (desde
 * panel SuperAdmin o automticamente al loggearse, etc).
 *
 * Item 7 del roadmap de visibilidad de cupones  push proactivo para
 * que el user se entere aunque no entre al sitio.
 *
 * Dispatched desde MarketplaceCouponService::assignToUser despus de
 * crear una NUEVA asignacin (si era idempotente con existing no dispara).
 */
class CouponAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public MarketplaceUser $user;
    public MarketplaceCoupon $coupon;
    public ?\DateTimeInterface $expiresAt;

    public function __construct(MarketplaceUser $user, MarketplaceCoupon $coupon, ?\DateTimeInterface $expiresAt = null)
    {
        $this->user = $user;
        $this->coupon = $coupon;
        $this->expiresAt = $expiresAt ?: $coupon->valid_until;
    }

    public function build()
    {
        $subject = '' . $this->coupon->code . ' - Tienes un cupn nuevo en ' . config('app.name', 'ebaemy');
        return $this->subject($subject)
                    ->view('emails.marketplace.coupon_assigned');
    }
}
