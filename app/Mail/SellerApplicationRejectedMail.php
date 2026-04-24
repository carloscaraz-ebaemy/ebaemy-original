<?php

namespace App\Mail;

use App\Models\System\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica al seller que su solicitud fue rechazada + motivo.
 */
class SellerApplicationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public SellerApplication $application;
    public string $reason;

    public function __construct(SellerApplication $application, string $reason)
    {
        $this->application = $application;
        $this->reason      = $reason;
    }

    public function build()
    {
        $appName = config('app.name', 'ebaemy');
        $fromAddress = config('mail.from.address', 'no-reply@ebaemy.com');

        return $this->subject("[{$appName}] Respuesta a tu solicitud de vendedor")
                    ->from($fromAddress, $appName)
                    ->view('emails.seller.rejected');
    }
}
