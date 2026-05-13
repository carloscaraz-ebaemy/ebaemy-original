<?php

namespace App\Mail;

use App\Models\System\MarketplaceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Recordatorio al COMPRADOR de pedido marketplace abandonado: registró el
 * pedido pero no completó el pago. El email lista los productos, el total
 * y un CTA de re-checkout.
 *
 * Se envía máximo 2 veces por pedido (espaciados 24h+) y solo si el pedido
 * sigue en status=pending tras 2h y aún está dentro de los 7 días.
 *
 * Dispatch lo hace el artisan command marketplace:remind-abandoned-orders
 * desde el scheduler (corre cada hora).
 */
class MarketplaceAbandonedOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public MarketplaceOrder $order) {}

    public function build()
    {
        $this->order->loadMissing('items');

        $subject = $this->order->reminder_count >= 1
            ? '🛍️ Tu pedido ' . $this->order->order_number . ' aún espera pago — última recordación'
            : '🛍️ ¿Olvidaste algo? Tu pedido ' . $this->order->order_number . ' sigue esperando';

        return $this
            ->subject($subject)
            ->view('emails.marketplace_abandoned_order', [
                'order'       => $this->order,
                'items'       => $this->order->items,
                'checkoutUrl' => url('/marketplace/order/' . $this->order->order_number),
                'isFinal'     => $this->order->reminder_count >= 1,
            ]);
    }
}
