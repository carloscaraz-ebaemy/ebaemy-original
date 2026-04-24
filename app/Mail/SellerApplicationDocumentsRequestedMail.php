<?php

namespace App\Mail;

use App\Models\System\SellerApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Pide al seller documentos adicionales antes de aprobar.
 * Incluye link al portal de seguimiento con su tracking_token.
 */
class SellerApplicationDocumentsRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public SellerApplication $application;
    public string $documentsRequested;
    public string $trackingUrl;

    public function __construct(SellerApplication $application, string $documentsRequested)
    {
        $this->application        = $application;
        $this->documentsRequested = $documentsRequested;
        $this->trackingUrl        = $application->tracking_token
            ? url('/seller/application/' . $application->tracking_token)
            : url('/seller');
    }

    public function build()
    {
        $appName = config('app.name', 'ebaemy');
        $fromAddress = config('mail.from.address', 'no-reply@ebaemy.com');

        return $this->subject("[{$appName}] Necesitamos información adicional sobre tu solicitud")
                    ->from($fromAddress, $appName)
                    ->view('emails.seller.documents_requested');
    }
}
