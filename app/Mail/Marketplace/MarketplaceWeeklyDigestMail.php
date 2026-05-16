<?php

namespace App\Mail\Marketplace;

use App\Models\System\MarketplaceUser;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Digest semanal de ofertas en categorias favoritas del comprador.
 * Marketing — requiere consent.
 */
class MarketplaceWeeklyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array $offers  Cada item: ['title','slug','image_url',
     *                                   'price','original_price','discount_pct',
     *                                   'category_name']
     * @param array $categoryNames Top categorias seguidas (display).
     */
    public function __construct(
        public MarketplaceUser $user,
        public array $offers,
        public array $categoryNames,
    ) {}

    public function build()
    {
        $catList = !empty($this->categoryNames)
            ? implode(', ', array_slice($this->categoryNames, 0, 3))
            : 'tus categorias';
        return $this
            ->from(config('mail.from.address') ?: 'no-reply@ebaemy.com', 'ebaemy')
            ->replyTo('soporte@ebaemy.com', 'Soporte ebaemy')
            ->subject('Tu resumen semanal: ofertas en ' . $catList)
            ->view('emails.marketplace_weekly_digest', [
                'user'           => $this->user,
                'offers'         => $this->offers,
                'category_names' => $this->categoryNames,
            ]);
    }
}
