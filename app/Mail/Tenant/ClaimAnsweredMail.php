<?php

namespace App\Mail\Tenant;

use App\Models\Tenant\Claim;
use Illuminate\Mail\Mailable;

/**
 * Respuesta del proveedor al consumidor. Lleva el código, la fecha de
 * presentación, la respuesta, las acciones adoptadas y —cuando el pedido no se
 * acoge— la fundamentación, que es lo que el reglamento exige comunicar.
 */
class ClaimAnsweredMail extends Mailable
{
    public Claim $claim;
    public array $provider;

    public function __construct(Claim $claim, array $provider)
    {
        $this->claim    = $claim;
        $this->provider = $provider;
    }

    public function build()
    {
        $mail = $this->subject('Respuesta a tu ' . strtolower($this->claim->typeLabel()) . ' ' . $this->claim->code)
            ->from(config('mail.from.address'), $this->provider['trade_name'] ?: config('mail.from.name'))
            ->view('tenant.templates.email.claim_answer')
            ->with([
                'claim'    => $this->claim,
                'provider' => $this->provider,
            ]);

        if ($replyTo = $this->provider['email'] ?? null) {
            $mail->replyTo($replyTo, $this->provider['trade_name']);
        }

        return $mail;
    }
}
