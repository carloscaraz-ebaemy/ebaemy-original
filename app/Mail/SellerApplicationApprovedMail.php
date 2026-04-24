<?php

namespace App\Mail;

use App\Models\System\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica al seller que su solicitud fue aprobada.
 *
 * NO incluye la contraseña en el mail: el seller la eligió durante el
 * registro y ya la conoce. El mail solo confirma la URL de la tienda
 * y el email con el que debe ingresar, sin exponer credenciales
 * sensibles en bandeja de entrada.
 */
class SellerApplicationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public SellerApplication $application;
    public string $tenantUrl;

    public function __construct(SellerApplication $application)
    {
        $this->application = $application;

        $scheme = config('tenant.force_https') === true ? 'https://' : 'http://';
        $this->tenantUrl = $scheme
            . $application->requested_subdomain
            . '.'
            . config('tenant.app_url_base');
    }

    public function build()
    {
        $appName = config('app.name', 'ebaemy');
        $fromAddress = config('mail.from.address', 'no-reply@ebaemy.com');

        return $this->subject("[{$appName}] ¡Tu tienda fue aprobada!")
                    ->from($fromAddress, $appName)
                    ->view('emails.seller.approved');
    }
}
