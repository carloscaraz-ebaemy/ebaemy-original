<?php

namespace App\Mail;

use App\Models\System\MarketplaceOrder;
use App\Models\System\TenantMarketplaceOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Notificacion al VENDEDOR de un pedido marketplace que cayó en su tienda.
 *
 * Se envia uno por cada tenant involucrado en el pedido multi-tienda.
 * Antes esto se enviaba con Mail::send([], [], closure) que en algunas
 * configs de driver fallaba silenciosamente. Ahora usa el patron Mailable
 * estandar (igual que MarketplaceOrderConfirmationMail al comprador).
 */
class MarketplaceTenantOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public MarketplaceOrder $order,
        public TenantMarketplaceOrder $sub,
        public Collection $items,
        public float $subtotal,
        public string $tenantFqdn
    ) {}

    public function build()
    {
        $subject = '🛍️ Pedido marketplace ' . $this->order->order_number
                 . ' (' . $this->items->count() . ' producto' . ($this->items->count() === 1 ? '' : 's') . ')';
        // Sanitizar: SMTP rechaza newlines + control chars en headers.
        $safeSubject = mb_substr(trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $subject))), 0, 100);

        return $this
            ->subject($safeSubject)
            ->view('emails.marketplace_tenant_order', [
                'order'       => $this->order,
                'sub'         => $this->sub,
                'items'       => $this->items,
                'subtotal'    => $this->subtotal,
                'tenantFqdn'  => $this->tenantFqdn,
                'panelUrl'    => 'https://' . $this->tenantFqdn . '/orders',
            ]);
    }
}
