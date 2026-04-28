<?php

namespace App\Services\System;

use App\Models\System\Client;
use App\Models\System\MarketplaceOrder;
use App\Models\System\MarketplaceOrderItem;
use App\Models\System\TenantMarketplaceOrder;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Despacha un MarketplaceOrder padre creando una Order dentro de cada tenant
 * involucrado. Una sola compra del cliente puede generar múltiples Orders en
 * varios tenants (uno por tienda).
 *
 * Diferencias con MarketplaceOrderDispatcher:
 *   - Procesa N items por tenant (no 1 por lead)
 *   - El comprador ya pasó por checkout con dirección/datos completos
 *   - Cada tenant recibe su Order propio, factura por separado
 *   - El padre vive en la BD central, los hijos en cada BD tenant
 *
 * Uso:
 *   $dispatcher->dispatchOrder($marketplaceOrder);
 */
class MarketplaceMultiOrderDispatcher
{
    /**
     * Despacha un MarketplaceOrder ya persistido (con items y subpedidos
     * tenant_marketplace_orders pre-creados en pending).
     *
     * @return array{success_count: int, failed_count: int, results: array}
     */
    public function dispatchOrder(MarketplaceOrder $order): array
    {
        $subOrders = $order->tenantOrders()
            ->where('status', TenantMarketplaceOrder::STATUS_PENDING)
            ->get();

        $items = $order->items()->get()->groupBy('hostname_id');

        $successCount = 0;
        $failedCount  = 0;
        $results      = [];

        foreach ($subOrders as $sub) {
            $tenantItems = $items->get($sub->hostname_id, collect());
            if ($tenantItems->isEmpty()) {
                $sub->update([
                    'status'     => TenantMarketplaceOrder::STATUS_FAILED,
                    'sync_error' => 'Sin items para esta tienda',
                ]);
                $failedCount++;
                continue;
            }

            $result = $this->dispatchTenantSubOrder($order, $sub, $tenantItems);
            $results[$sub->hostname_id] = $result;

            if ($result['success'] ?? false) {
                $successCount++;
            } else {
                $failedCount++;
            }
        }

        $order->refresh();
        $order->recomputeStatus();

        return [
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
            'results'       => $results,
        ];
    }

    /**
     * Crea una Order en la BD del tenant para los items de un solo seller.
     * Idéntico patrón a MarketplaceOrderDispatcher pero con N items.
     */
    private function dispatchTenantSubOrder(
        MarketplaceOrder $order,
        TenantMarketplaceOrder $sub,
        Collection $items
    ): array {
        $client = Client::where('hostname_id', $sub->hostname_id)
            ->with('hostname.website')
            ->first();

        if (!$client || !$client->hostname || !$client->hostname->website) {
            $sub->update([
                'status'     => TenantMarketplaceOrder::STATUS_FAILED,
                'sync_error' => 'Tenant no disponible',
            ]);
            return ['success' => false, 'error' => 'Tenant no disponible'];
        }

        $tenancy = app(Environment::class);
        $previousTenant = $tenancy->tenant();

        try {
            $tenancy->tenant($client->hostname->website);

            $channel = DB::connection('tenant')->table('sales_channels')
                ->where('code', 'MKP01')->first();

            if (!$channel) {
                throw new \RuntimeException('Canal MKP01 no configurado en tenant');
            }

            if (!$channel->is_active) {
                DB::connection('tenant')->table('sales_channels')
                    ->where('id', $channel->id)
                    ->update(['is_active' => true, 'updated_at' => now()]);
            }

            // Resolver items reales en la BD del tenant para asegurar que
            // existen y tomar su precio canónico (snapshot del marketplace
            // se respeta en el JSON, pero validamos existencia).
            $remoteIds = $items->pluck('remote_item_id')->all();
            $tenantItems = DB::connection('tenant')->table('items')
                ->whereIn('id', $remoteIds)
                ->get(['id', 'description', 'sale_unit_price', 'internal_id'])
                ->keyBy('id');

            $jsonItems = [];
            $orderSubtotal = 0;
            foreach ($items as $line) {
                $remote = $tenantItems->get($line->remote_item_id);
                if (!$remote) {
                    Log::warning('marketplace dispatch: item no existe en tenant', [
                        'order_id'  => $order->id,
                        'item_id'   => $line->remote_item_id,
                        'tenant'    => $client->hostname->fqdn,
                    ]);
                    continue;
                }
                $unit  = (float) $line->unit_price;
                $qty   = (int) $line->quantity;
                $total = round($unit * $qty, 2);
                $orderSubtotal += $total;

                $jsonItems[] = [
                    'id'              => (int) $remote->id,
                    'description'     => $remote->description ?? $line->title,
                    'sale_unit_price' => $unit,
                    'unit_price'      => $unit,
                    'quantity'        => $qty,
                    'subtotal'        => $total,
                    'internal_id'     => $remote->internal_id ?? null,
                ];
            }

            if (empty($jsonItems)) {
                throw new \RuntimeException('Ningún item de esta tienda existe ya en el catálogo');
            }

            $externalId = (string) Str::uuid();
            $deliveryAddr = trim(implode(', ', array_filter([
                $order->delivery_address,
                $order->delivery_district,
                $order->delivery_province,
                $order->delivery_department,
            ])));

            $orderId = DB::connection('tenant')->table('orders')->insertGetId([
                'external_id'       => $externalId,
                'customer'          => json_encode([
                    'apellidos_y_nombres_o_razon_social' => $order->customer_name,
                    'numero'                             => $order->customer_doc_number,
                    'tipo_documento'                     => $order->customer_doc_type,
                    'telefono'                           => $order->customer_phone,
                    'correo_electronico'                 => $order->customer_email,
                    'source'                             => 'marketplace_ebaemy',
                ]),
                'shipping_address'  => $deliveryAddr ?: 'Por definir',
                'items'             => json_encode($jsonItems),
                'total'             => $orderSubtotal,
                'subtotal'          => $orderSubtotal,
                'total_discount'    => 0,
                'reference_payment' => 'marketplace',
                'status_order_id'   => 1, // Pendiente
                'channel_id'        => $channel->id,
                'warehouse_id'      => $channel->warehouse_id,
                'seller_id'         => null,
                'marketplace_notes' => "Marketplace pedido {$order->order_number} — " . ($order->delivery_notes ?? '-'),
                'external_order_ref'=> $order->order_number,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            try {
                $titleSummary = $items->count() === 1
                    ? $items->first()->title
                    : $items->count() . ' productos';
                dispatch(\App\Jobs\SendWhatsAppMessage::adminMarketplaceOrder(
                    $order->customer_name,
                    (string) $orderId,
                    (float) $orderSubtotal,
                    $titleSummary,
                    (int) $items->sum('quantity'),
                    $order->customer_phone
                ));
            } catch (\Throwable $e) {
                Log::warning('marketplace multi WhatsApp dispatch failed', [
                    'order' => $order->order_number,
                    'error' => $e->getMessage(),
                ]);
            }

            // Volver a sistema central
            $tenancy->tenant(null);

            $sub->update([
                'tenant_order_id'          => $orderId,
                'tenant_order_external_id' => $externalId,
                'client_id'                => $client->id,
                'status'                   => TenantMarketplaceOrder::STATUS_DISPATCHED,
                'dispatched_at'            => now(),
                'sync_error'               => null,
            ]);

            $this->notifyTenant($client, $order, $sub, $items, $orderSubtotal);

            return [
                'success'        => true,
                'tenant_fqdn'    => $client->hostname->fqdn,
                'order_id'       => $orderId,
                'external_id'    => $externalId,
            ];
        } catch (\Throwable $e) {
            Log::error('MarketplaceMultiOrderDispatcher failed for tenant', [
                'order_id'    => $order->id,
                'hostname_id' => $sub->hostname_id,
                'error'       => $e->getMessage(),
            ]);
            $sub->update([
                'status'      => TenantMarketplaceOrder::STATUS_FAILED,
                'sync_error'  => Str::limit($e->getMessage(), 480),
                'retry_count' => $sub->retry_count + 1,
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            // Restaurar el tenant que estaba activo antes (si lo había)
            $tenancy->tenant($previousTenant ?: null);
        }
    }

    /**
     * Email al dueño del tenant con resumen del subpedido. Non-blocking.
     */
    protected function notifyTenant(
        Client $client,
        MarketplaceOrder $order,
        TenantMarketplaceOrder $sub,
        Collection $items,
        float $subtotal
    ): void {
        if (empty($client->email)) {
            return;
        }

        try {
            $tenantFqdn = $client->hostname->fqdn;
            $itemsHtml = '';
            foreach ($items as $line) {
                $itemsHtml .= '<tr><td style="padding:6px 0">' . htmlspecialchars($line->title)
                    . ' <span style="color:#9ca3af">×' . (int) $line->quantity . '</span></td>'
                    . '<td style="padding:6px 0;text-align:right">S/ ' . number_format($line->total, 2) . '</td></tr>';
            }

            $html  = '<!DOCTYPE html><html><body style="font-family:sans-serif;color:#333;max-width:560px;margin:0 auto;padding:24px">';
            $html .= '<div style="background:linear-gradient(135deg,#0f8a82 0%,#0a6f68 100%);color:#fff;padding:20px;border-radius:12px 12px 0 0;text-align:center">';
            $html .= '<h2 style="margin:0;font-size:20px">🛍️ Nuevo pedido desde Marketplace ebaemy</h2></div>';
            $html .= '<div style="background:#fff;border:1px solid #e5e7eb;border-top:none;padding:24px;border-radius:0 0 12px 12px">';
            $html .= '<p>Hola, llegó un pedido a tu tienda desde <strong>ebaemy.com/marketplace</strong>.</p>';
            $html .= '<p style="font-size:13px;color:#6b7280">Pedido marketplace: <strong>' . htmlspecialchars($order->order_number) . '</strong></p>';
            $html .= '<table style="width:100%;border-collapse:collapse;margin:16px 0">' . $itemsHtml;
            $html .= '<tr><td style="padding:10px 0;border-top:1px solid #e5e7eb;font-weight:600">Total tu subpedido</td>';
            $html .= '<td style="padding:10px 0;border-top:1px solid #e5e7eb;text-align:right;font-weight:700">S/ ' . number_format($subtotal, 2) . '</td></tr>';
            $html .= '</table>';
            $html .= '<div style="background:#fef3c7;border-radius:10px;padding:16px;margin:20px 0">';
            $html .= '<div style="font-weight:600;color:#92400e;margin-bottom:6px">👤 Cliente</div>';
            $html .= '<div><strong>' . htmlspecialchars($order->customer_name) . '</strong>';
            if ($order->customer_doc_number) {
                $html .= ' · ' . htmlspecialchars($order->customer_doc_type . ' ' . $order->customer_doc_number);
            }
            $html .= '</div>';
            $html .= '<div>📱 ' . htmlspecialchars($order->customer_phone) . '</div>';
            if ($order->customer_email) {
                $html .= '<div>✉️ ' . htmlspecialchars($order->customer_email) . '</div>';
            }
            $html .= '<div style="margin-top:8px;font-size:13px"><strong>Dirección:</strong> ' . htmlspecialchars($order->delivery_address);
            if ($order->delivery_district) {
                $html .= ' — ' . htmlspecialchars($order->delivery_district);
            }
            $html .= '</div>';
            if ($order->delivery_notes) {
                $html .= '<div style="margin-top:6px;font-size:13px">💬 ' . htmlspecialchars($order->delivery_notes) . '</div>';
            }
            $html .= '</div>';
            $html .= '<a href="https://' . $tenantFqdn . '/orders" style="display:inline-block;background:#111;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600">Ver pedido en mi panel →</a>';
            $html .= '</div></body></html>';

            $subject = '🛍️ Pedido marketplace ' . $order->order_number . ' (' . $items->count() . ' productos)';
            $safeSubject = mb_substr(trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $subject))), 0, 100);

            Mail::send([], [], function ($message) use ($client, $safeSubject, $html) {
                $message->to($client->email)->subject($safeSubject)->html($html);
            });
        } catch (\Throwable $e) {
            Log::warning('marketplace multi-order tenant notification failed', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
