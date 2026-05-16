<?php

namespace App\Mail\Marketplace;

use App\Models\System\MarketplaceUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Email de bienvenida al crear cuenta en el marketplace.
 * Transaccional — no requiere consent.
 *
 * Se dispara una sola vez, desde:
 *   - MarketplaceAuthService::register() (form con password)
 *   - MarketplaceAuthService::loginOrRegisterGoogle() (si es user nuevo)
 *   - MarketplaceAuthService::verify() (magic link, si crea cuenta pasiva)
 */
class MarketplaceWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MarketplaceUser $user,
        public bool $marketingOptIn = false,
    ) {}

    public function build()
    {
        $fromAddr = config('mail.from.address') ?: 'no-reply@ebaemy.com';
        return $this
            ->from($fromAddr, 'ebaemy')
            ->replyTo('soporte@ebaemy.com', 'Soporte ebaemy')
            ->subject('Bienvenido a ebaemy — tu cuenta esta lista')
            ->view('emails.marketplace_welcome', [
                'user'        => $this->user,
                'showOffersCta' => $this->marketingOptIn,
            ]);
    }
}
