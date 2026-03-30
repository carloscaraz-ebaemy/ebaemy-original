<?php

namespace App\Listeners\Ecommerce;

use App\Events\Ecommerce\OrderCreated;
use App\Jobs\SendWhatsAppMessage;
use App\Models\Tenant\Company;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendWhatsAppNotification
{
    /**
     * Envía WhatsApp inmediatamente (sin queue) al crear pedido.
     * Notifica al cliente y al admin/vendedor via QR Api.
     */
    public function handle(OrderCreated $event): void
    {
        $order       = $event->order;
        $orderNumber = strtoupper(substr($order->external_id, 0, 8));
        $total       = (float) $order->total;
        $items       = is_array($order->items) ? $order->items : (array) $order->items;
        $storeName   = optional(Company::first())->trade_name ?? optional(Company::first())->name ?? 'Tienda';

        $wa = new \App\Services\Tenant\WhatsAppService();

        if (!$wa->isEnabled()) {
            Log::debug('WhatsApp no configurado, notificaciones no enviadas');
            return;
        }

        // ── 1. Notificar al cliente ─────────────────────────────────────────
        if ($event->customerPhone) {
            try {
                $wa->notifyClientOrderReceived(
                    $event->customerPhone,
                    $event->customerName,
                    $orderNumber,
                    $total,
                    $storeName
                );
            } catch (\Throwable $e) {
                Log::warning('WhatsApp al cliente falló: ' . $e->getMessage());
            }
        }

        // ── 2. Notificar al admin/vendedor ──────────────────────────────────
        try {
            $wa->notifyAdminNewOrder(
                $event->customerName,
                $orderNumber,
                $total,
                $items
            );
        } catch (\Throwable $e) {
            Log::warning('WhatsApp al admin falló: ' . $e->getMessage());
        }
    }

    public function failed(OrderCreated $event, \Throwable $e): void
    {
        Log::error('SendWhatsAppNotification permanently failed', [
            'order_id' => $event->order->id,
            'error'    => $e->getMessage(),
        ]);
    }
}
