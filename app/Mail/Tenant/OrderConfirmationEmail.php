<?php

namespace App\Mail\Tenant;

use App\Models\Tenant\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationEmail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    public string $customerName;
    public string $customerEmail;

    public function __construct(Order $order, string $customerName, string $customerEmail)
    {
        $this->order         = $order;
        $this->customerName  = $customerName;
        $this->customerEmail = $customerEmail;
    }

    public function build(): self
    {
        $company = \App\Models\Tenant\Company::first();
        $subject = '¡Pedido recibido! #' . strtoupper(substr($this->order->external_id, 0, 8));

        return $this->subject($subject)
                    ->view('tenant.templates.email.order_confirmation')
                    ->with([
                        'order'         => $this->order,
                        'customerName'  => $this->customerName,
                        'customerEmail' => $this->customerEmail,
                        'company'       => $company,
                        'confirmUrl'    => url('/ecommerce/order/confirmation/' . $this->order->external_id),
                        'orderNumber'   => strtoupper(substr($this->order->external_id, 0, 8)),
                        'items'         => is_array($this->order->items)
                                            ? $this->order->items
                                            : (json_decode(json_encode($this->order->items), true) ?? []),
                        'total'         => number_format((float) $this->order->total, 2),
                    ]);
    }
}
