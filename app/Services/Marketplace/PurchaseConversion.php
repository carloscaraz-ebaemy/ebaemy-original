<?php

namespace App\Services\Marketplace;

use App\Jobs\Marketplace\SendAdsConversion;
use App\Models\System\MarketplaceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * El único sitio que manda el `Purchase` de un pedido del marketplace.
 *
 * Hay tres caminos distintos que llegan a «pedido pagado» y cada uno sabe
 * cosas diferentes:
 *
 *  1. Contra entrega o transferencia — el comprador está delante, con su
 *     sesión y su navegador.
 *  2. Vuelve de MercadoPago a la pantalla de confirmación — también está.
 *  3. **El webhook de MercadoPago** — aquí no hay comprador: la petición la
 *     hace MercadoPago desde sus servidores. Es el caso que se perdía: quien
 *     paga y nunca vuelve a la tienda no generaba ninguna conversión, y es
 *     justo el que más ocurre en móvil.
 *
 * Por eso el envío vive aquí y no en el controlador: los tres caminos usan la
 * misma guarda de idempotencia y el mismo payload, y sólo cambia de dónde
 * salen los datos del comprador.
 */
class PurchaseConversion
{
    /**
     * Manda el Purchase una sola vez por pedido.
     *
     * @param  Request|null $request  La petición del comprador, si la hay. En
     *                                el webhook se pasa null y los
     *                                identificadores salen del propio pedido.
     * @return bool  true si se encoló ahora, false si ya estaba mandado o la
     *               medición está apagada.
     */
    public function sendOnce(MarketplaceOrder $order, ?Request $request = null): bool
    {
        if (!AdsTracking::serverEnabled()) {
            return false;
        }

        // El comprador tiene que haber aceptado la medicion, y se mira en el
        // PEDIDO, no en la sesion: el webhook de la pasarela llega sin sesion
        // y si no, acabaria mandando a Meta y TikTok el email y el telefono de
        // alguien que pulso «Rechazar». Sin decision expresa tampoco se manda:
        // el banner no estaria sirviendo de nada.
        if (($order->ads_consent ?? null) !== 'granted') {
            return false;
        }

        if (!$this->claim($order)) {
            return false;
        }

        $payload = AdsTracking::payload(
            'purchase',
            $order->items->map(fn ($i) => [
                'content_id'   => AdsTracking::contentId($i->listing_id),
                'content_name' => (string) $i->title,
                'quantity'     => (int) $i->quantity,
                'price'        => round((float) $i->unit_price, 2),
            ])->values()->all(),
            (float) $order->total,
            // Sembrado con el número de pedido: el evento del navegador y el
            // del servidor llevan el MISMO event_id y la plataforma deduplica
            // en vez de contar la venta dos veces.
            $order->order_number
        );

        $payload['url'] = $request
            ? $request->fullUrl()
            : route('marketplace.order.confirmation', ['number' => $order->order_number]);

        SendAdsConversion::dispatch(
            'purchase',
            $payload,
            $request
                ? AdsTracking::userDataFromRequest($request)
                : AdsTracking::userDataFromOrder($order),
            [
                'email' => $order->customer_email,
                'phone' => $order->customer_phone,
            ]
        );

        return true;
    }

    /**
     * Reclama el envío con un UPDATE condicional.
     *
     * La guarda está en la base y no en una bandera en memoria porque los
     * tres caminos son peticiones distintas —y el webhook puede llegar a la
     * vez que el comprador vuelve de la pasarela—. Un `Purchase` duplicado
     * infla el ROAS de la cuenta publicitaria y no se puede corregir después.
     */
    private function claim(MarketplaceOrder $order): bool
    {
        try {
            return (bool) MarketplaceOrder::where('id', $order->id)
                ->whereNull('ads_purchase_sent_at')
                ->update(['ads_purchase_sent_at' => now()]);
        } catch (\Throwable $e) {
            // Si no se puede reclamar, no se manda: es preferible perder una
            // conversión a arriesgarse a contarla dos veces.
            Log::warning('PurchaseConversion: no se pudo reclamar el envío', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
