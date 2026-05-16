<?php

namespace App\Mail\Marketplace;

use App\Models\System\MarketplaceUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso de bajadas de precio en favoritos del comprador.
 * Marketing/price_alerts — requiere consent.
 */
class MarketplacePriceDropMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $drops  Cada item: ['title', 'slug', 'image_url',
     *                                    'old_price', 'new_price', 'saving_pct']
     */
    public function __construct(
        public MarketplaceUser $user,
        public array $drops,
    ) {}

    public function build()
    {
        $count = count($this->drops);
        $subject = $count === 1
            ? 'Bajo de precio un producto que guardaste'
            : "$count productos que guardaste bajaron de precio";
        return $this
            ->from(config('mail.from.address') ?: 'no-reply@ebaemy.com', 'ebaemy')
            ->replyTo('soporte@ebaemy.com', 'Soporte ebaemy')
            ->subject($subject)
            ->view('emails.marketplace_price_drop', [
                'user'  => $this->user,
                'drops' => $this->drops,
                'count' => $count,
            ]);
    }
}
