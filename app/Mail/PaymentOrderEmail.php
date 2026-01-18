<?php

namespace App\Mail;

use App\Models\System\PaymentOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class PaymentOrderEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    public $order;
    public function __construct(PaymentOrder $order)
    {
        $this->order = $order;
    }

    /**
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('system.payments.template.email')
                    ->subject("Notificación de Orden de Pago {$this->order->order}")
                    ->from(config('mail.username'));
    }
}
