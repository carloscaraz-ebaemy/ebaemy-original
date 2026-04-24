<?php

namespace App\Mail;

use App\Models\System\Client;
use App\Models\System\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Confirma que la tienda virtual fue activada en un tenant preexistente.
 *
 * No entrega credenciales porque el seller ya las tenía desde su
 * onboarding original. Solo confirma la activación y da el link al panel.
 */
class SellerActivationApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public SellerApplication $application;
    public Client $client;
    public string $tenantUrl;

    public function __construct(SellerApplication $application, Client $client)
    {
        $this->application = $application;
        $this->client      = $client;

        $hostname = optional($client->hostname)->fqdn ?? $client->hostname()->value('fqdn');
        $scheme = config('tenant.force_https') === true ? 'https://' : 'http://';
        $this->tenantUrl = $hostname
            ? $scheme . $hostname
            : url('/');
    }

    public function build()
    {
        $appName = config('app.name', 'ebaemy');
        $fromAddress = config('mail.from.address', 'no-reply@ebaemy.com');

        return $this->subject("[{$appName}] ¡Tu tienda virtual fue activada!")
                    ->from($fromAddress, $appName)
                    ->view('emails.seller.activation_approved');
    }
}
