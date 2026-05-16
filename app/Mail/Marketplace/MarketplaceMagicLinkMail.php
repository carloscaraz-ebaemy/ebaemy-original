<?php

namespace App\Mail\Marketplace;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Magic link de acceso al marketplace.
 *
 * Contiene el link completo (1-tap desktop) Y el codigo de 6 digitos
 * (necesario en mobile, donde el link puede abrir otro navegador sin
 * la sesion del que pidio el codigo).
 *
 * Transaccional: NO requiere consent de marketing.
 */
class MarketplaceMagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $email,
        public string $token,
        public string $code,
    ) {}

    public function build()
    {
        $fromAddr = config('mail.from.address') ?: 'no-reply@ebaemy.com';

        return $this
            ->from($fromAddr, 'ebaemy')
            ->replyTo('soporte@ebaemy.com', 'Soporte ebaemy')
            ->subject('Tu acceso a ebaemy — Codigo: ' . $this->code)
            ->view('emails.marketplace_magic_link', [
                'email'    => $this->email,
                'code'     => $this->code,
                'link'     => url('/marketplace/auth/verify?token=' . $this->token),
                'ttl_min'  => 15,
            ]);
    }
}
