<?php

namespace App\Mail;

use App\Models\System\MarketplaceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmación al COMPRADOR (no al seller) del pedido multi-tienda creado
 * desde ebaemy.com/marketplace. Resume las tiendas involucradas, los items
 * por tienda y advierte que cada tienda contactará por separado.
 *
 * Email del comprador es opcional en checkout; si no lo dejó, este mail no
 * se envía.
 */
class MarketplaceOrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MarketplaceOrder $order) {}

    public function build()
    {
        $this->order->loadMissing(['items', 'tenantOrders']);

        $fromAddr = config('mail.from.address') ?: 'no-reply@ebaemy.com';

        return $this
            ->from($fromAddr, 'ebaemy Marketplace')
            ->replyTo('soporte@ebaemy.com', 'Soporte ebaemy')
            ->subject('Tu pedido ' . $this->order->order_number . ' fue recibido — ebaemy')
            ->view('emails.marketplace_order_confirmation', [
                'order'        => $this->order,
                'itemsByStore' => $this->order->items->groupBy('hostname_id'),
                'subOrders'    => $this->order->tenantOrders->keyBy('hostname_id'),
                'trackingUrl'  => url('/marketplace/order/' . $this->order->order_number),
            ]);
    }
}
