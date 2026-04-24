<?php

namespace App\Mail;

use App\Models\System\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica al seller que su solicitud fue aprobada + credenciales temporales.
 * El seller deberá cambiar la contraseña al primer login.
 */
class SellerApplicationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public SellerApplication $application;
    public string $temporaryPassword;
    public string $tenantUrl;

    public function __construct(SellerApplication $application, string $temporaryPassword)
    {
        $this->application       = $application;
        $this->temporaryPassword = $temporaryPassword;

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
