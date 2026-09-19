<?php

namespace App\Mail\Tenant;

use App\Models\Tenant\Claim;
use Illuminate\Mail\Mailable;

/**
 * Aviso al tenant: entró una hoja nueva y el reloj de los 15 días hábiles ya
 * está corriendo.
 */
class ClaimRegisteredTenantMail extends Mailable
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
        $mail = $this->subject('[' . $this->claim->typeLabel() . ' ' . $this->claim->code . '] ' . $this->claim->fullName())
            ->from(config('mail.from.address'), 'Libro de Reclamaciones')
            ->view('tenant.templates.email.claim_tenant')
            ->with([
                'claim'    => $this->claim,
                'provider' => $this->provider,
            ]);

        // Responder al aviso escribe al consumidor, que es lo que el
        // encargado va a intentar hacer de todos modos.
        if ($this->claim->email) {
            $mail->replyTo($this->claim->email, $this->claim->fullName());
        }

        foreach ((array) $this->claim->files as $file) {
            $path = storage_path('app/public/' . $file);

            if (is_file($path)) {
                $mail->attach($path, ['as' => basename($path)]);
            }
        }

        return $mail;
    }
}
