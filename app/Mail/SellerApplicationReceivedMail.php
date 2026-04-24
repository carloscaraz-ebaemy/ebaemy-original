<?php

namespace App\Mail;

use App\Models\System\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Notifica al seller que su solicitud fue recibida y está en revisión.
 */
class SellerApplicationReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public SellerApplication $application;

    public function __construct(SellerApplication $application)
    {
        $this->application = $application;
    }

    public function build()
    {
        $appName = config('app.name', 'ebaemy');
        $fromAddress = config('mail.from.address', 'no-reply@ebaemy.com');

        return $this->subject("[{$appName}] Recibimos tu solicitud de vendedor")
                    ->from($fromAddress, $appName)
                    ->view('emails.seller.received');
    }
}
