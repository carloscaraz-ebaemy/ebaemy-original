<?php

namespace App\Mail\Tenant;

use App\Models\Tenant\AbandonedCart;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AbandonedCartReminderEmail extends Mailable
{
    use Queueable, SerializesModels;

    public AbandonedCart $cart;
    public string $storeName;
    public string $storeUrl;
    public string $cartUrl;
    public int $step;
    public ?string $discountCode;

    public function __construct(AbandonedCart $cart, string $storeName, string $storeUrl, int $step = 1, ?string $discountCode = null)
    {
        $this->cart         = $cart;
        $this->storeName    = $storeName;
        $this->storeUrl     = $storeUrl;
        $this->cartUrl      = $storeUrl . '/cart?restore_token=' . urlencode($cart->session_token);
        $this->step         = $step;
        $this->discountCode = $discountCode;
    }

    public function build(): self
    {
        $firstName = $this->cart->customer_name
            ? explode(' ', trim($this->cart->customer_name))[0]
            : 'Cliente';

        $subjects = [
            1 => "¡{$firstName}, olvidaste algo en tu carrito!",
            2 => "¡{$firstName}, tu carrito te espera! Stock limitado",
            3 => "¡{$firstName}, 10% OFF solo para ti! Completa tu compra",
        ];

        return $this->subject($subjects[$this->step] ?? $subjects[1])
                    ->view('tenant.templates.email.abandoned_cart_reminder')
                    ->with([
                        'cart'         => $this->cart,
                        'firstName'    => $firstName,
                        'items'        => $this->cart->items ?? [],
                        'subtotal'     => number_format((float) $this->cart->subtotal, 2),
                        'storeName'    => $this->storeName,
                        'cartUrl'      => $this->cartUrl,
                        'storeUrl'     => $this->storeUrl,
                        'step'         => $this->step,
                        'discountCode' => $this->discountCode,
                    ]);
    }
}
