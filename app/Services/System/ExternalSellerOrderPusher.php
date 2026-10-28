<?php

namespace App\Services\System;

use App\Models\System\MarketplaceExternalSeller;
use App\Models\System\MarketplaceOrder;
use App\Models\System\TenantMarketplaceOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Entrega un subpedido a un vendedor externo (PRO 9) por webhook firmado, en
 * lugar de escribirlo en la BD de un tenant local.
 *
 *   POST {webhook_url}/api/ebaemy/orders
 *   X-Seller-Key / X-Timestamp / X-Signature  (mismo esquema que la API de
 *   vendedores, ver VerifyExternalSellerSignature)
 *
 * El vendedor responde {"order_id": <id en su sistema>}. `external_id` es fijo
 * por subpedido, así que un reintento no duplica el pedido en el vendedor.
 */
class ExternalSellerOrderPusher
{
    /** @return int id del pedido en el sistema del vendedor */
    public function push(MarketplaceExternalSeller $seller, MarketplaceOrder $order, TenantMarketplaceOrder $sub, Collection $items): int
    {
        if (!$seller->webhook_url) {
            throw new \RuntimeException('El vendedor externo no tiene URL de pedidos configurada');
        }

        $externalId = $sub->tenant_order_external_id ?: (string) Str::uuid();
        if (!$sub->tenant_order_external_id) {
            $sub->update(['tenant_order_external_id' => $externalId]);
        }

        $subtotal = round((float) $items->sum(fn ($l) => (float) $l->unit_price * (int) $l->quantity), 2);
        $discount = round((float) ($sub->discount_amount ?? 0), 2);

        $payload = [
            'external_id'  => $externalId,
            'order_number' => $order->order_number,
            'paid'         => $order->payment_status === 'paid',
            'paid_at'      => optional($order->payment_paid_at)->toIso8601String(),
            'customer'     => [
                'name'     => $order->customer_name,
                'doc_type' => $order->customer_doc_type,
                'doc'      => $order->customer_doc_number,
                'phone'    => $order->customer_phone,
                'email'    => $order->customer_email,
            ],
            'delivery'     => [
                'address'    => $order->delivery_address,
                'district'   => $order->delivery_district,
                'province'   => $order->delivery_province,
                'department' => $order->delivery_department,
                'notes'      => $order->delivery_notes,
            ],
            'items'        => $items->map(fn ($l) => [
                'remote_item_id' => (int) $l->remote_item_id,
                'variant_id'     => $l->tenant_variant_id ? (int) $l->tenant_variant_id : null,
                'variant_name'   => $l->variant_name ?: null,
                'title'          => $l->title,
                'quantity'       => (int) $l->quantity,
                'unit_price'     => (float) $l->unit_price,
            ])->values()->all(),
            'subtotal'     => $subtotal,
            'discount'     => $discount,
            'total'        => round(max(0, $subtotal - $discount), 2),
        ];

        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();

        $response = Http::timeout(15)
            ->acceptJson()
            ->withHeaders([
                'X-Seller-Key' => $seller->api_key,
                'X-Timestamp'  => $timestamp,
                'X-Signature'  => $seller->sign($timestamp, $body),
            ])
            ->withBody($body, 'application/json')
            ->post(rtrim($seller->webhook_url, '/') . '/api/ebaemy/orders');

        $orderId = (int) $response->json('order_id');
        if (!$response->successful() || $orderId <= 0) {
            throw new \RuntimeException('El vendedor no aceptó el pedido: HTTP ' . $response->status() . ' '
                . Str::limit((string) ($response->json('message') ?? $response->body()), 200));
        }

        return $orderId;
    }
}
