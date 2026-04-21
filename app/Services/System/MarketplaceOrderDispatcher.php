<?php

namespace App\Services\System;

use App\Models\System\Client;
use App\Models\System\MarketplaceLead;
use App\Models\System\MarketplaceListing;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Toma un lead del marketplace central y crea una Order en la BD del tenant
 * correspondiente usando el canal 'Marketplace ebaemy' (code=MKP01). La Order
 * queda en status_order_id=1 (Pendiente) para que el tenant la confirme/pague
 * desde su panel.
 */
class MarketplaceOrderDispatcher
{
    public function dispatchLead(MarketplaceLead $lead): bool
    {
        $listing = $lead->listing;
        if (!$listing || !$listing->hostname_id) {
            $lead->update(['status' => 'failed', 'sync_error' => 'Listing sin hostname']);
            return false;
        }

        $client = Client::where('hostname_id', $listing->hostname_id)->first();
        if (!$client || !$client->hostname || !$client->hostname->website) {
            $lead->update(['status' => 'failed', 'sync_error' => 'Tenant no disponible']);
            return false;
        }

        $tenancy = app(Environment::class);
        try {
            $tenancy->tenant($client->hostname->website);

            $channel = DB::connection('tenant')->table('sales_channels')
                ->where('code', 'MKP01')->first();

            if (!$channel) {
                $lead->update(['status' => 'failed', 'sync_error' => 'Canal MKP01 no configurado en tenant']);
                return false;
            }

            // Activar canal automáticamente si estaba en false — primera venta MP
            if (!$channel->is_active) {
                DB::connection('tenant')->table('sales_channels')
                    ->where('id', $channel->id)->update(['is_active' => true, 'updated_at' => now()]);
            }

            $item = DB::connection('tenant')->table('items')
                ->where('id', $listing->remote_item_id)->first();
            if (!$item) {
                $lead->update(['status' => 'failed', 'sync_error' => 'Item no existe en tenant']);
                return false;
            }

            $qty   = max(1, (int) $lead->quantity);
            $price = (float) ($listing->mp_price ?: $item->sale_unit_price ?: 0);
            $total = round($price * $qty, 2);

            $externalId = (string) Str::uuid();

            $orderId = DB::connection('tenant')->table('orders')->insertGetId([
                'external_id'       => $externalId,
                'customer'          => json_encode([
                    'apellidos_y_nombres_o_razon_social' => $lead->customer_name,
                    'telefono'                           => $lead->customer_phone,
                    'correo_electronico'                 => $lead->customer_email,
                    'source'                             => 'marketplace_ebaemy',
                ]),
                'shipping_address'  => 'Por definir (pedido marketplace)',
                'items'             => json_encode([[
                    'id'              => $item->id,
                    'description'     => $item->description,
                    'sale_unit_price' => $price,
                    'quantity'        => $qty,
                    'subtotal'        => $total,
                    'unit_price'      => $price,
                ]]),
                'total'             => $total,
                'subtotal'          => $total,
                'total_discount'    => 0,
                'reference_payment' => 'marketplace',
                'status_order_id'   => 1, // Pendiente
                'channel_id'        => $channel->id,
                'warehouse_id'      => $channel->warehouse_id,
                'seller_id'         => null,
                'marketplace_notes' => "Lead #{$lead->id} vía ebaemy.com/marketplace. Mensaje: " . ($lead->message ?? '-'),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            Log::info('marketplace lead converted to tenant order', [
                'lead_id'  => $lead->id,
                'tenant'   => $client->hostname->fqdn,
                'order_id' => $orderId,
            ]);

            // Volver al central para guardar estado
            $tenancy->tenant(null);

            $lead->update([
                'status'                   => 'converted',
                'tenant_order_external_id' => $externalId,
                'sync_error'               => null,
            ]);

            MarketplaceListing::where('id', $listing->id)->increment('lead_count');

            // Notificar al tenant — no bloqueante (swallow errors, ya hay orden creada)
            $this->notifyTenant($client, $lead, $listing, $externalId);

            return true;
        } catch (\Throwable $e) {
            Log::error('MarketplaceOrderDispatcher failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
            $tenancy->tenant(null);
            $lead->update(['status' => 'failed', 'sync_error' => Str::limit($e->getMessage(), 480)]);
            return false;
        } finally {
            $tenancy->tenant(null);
        }
    }

    /**
     * Avisa al dueño del tenant por email que llegó un nuevo pedido desde el
     * marketplace central. Si el SMTP del tenant está mal configurado o falla,
     * solo se loggea — la Order ya quedó creada y es lo crítico.
     */
    protected function notifyTenant(Client $client, MarketplaceLead $lead, MarketplaceListing $listing, string $externalId): void
    {
        $to = $client->email;
        if (!$to) {
            return;
        }

        try {
            $subject = '🛒 Nuevo pedido desde Marketplace ebaemy';
            $tenantFqdn = $client->hostname->fqdn;
            $total = number_format($lead->snapshot_price * $lead->quantity, 2);

            $html  = '<!DOCTYPE html><html><body style="font-family:sans-serif;color:#333;max-width:560px;margin:0 auto;padding:24px">';
            $html .= '<div style="background:linear-gradient(135deg,#8b5cf6 0%,#6366f1 100%);color:#fff;padding:20px;border-radius:12px 12px 0 0;text-align:center">';
            $html .= '<h2 style="margin:0;font-size:20px">🛒 Nuevo pedido desde Marketplace ebaemy</h2>';
            $html .= '</div>';
            $html .= '<div style="background:#fff;border:1px solid #e5e7eb;border-top:none;padding:24px;border-radius:0 0 12px 12px">';

            $html .= '<p>Hola, llegó un nuevo pedido desde <strong>ebaemy.com/marketplace</strong> para tu tienda.</p>';

            $html .= '<div style="background:#f9fafb;border-radius:10px;padding:16px;margin:20px 0">';
            $html .= '<div style="font-size:13px;color:#64748b;margin-bottom:4px">Producto</div>';
            $html .= '<div style="font-weight:600">' . htmlspecialchars($listing->title) . '</div>';
            $html .= '<div style="margin-top:10px;font-size:13px;color:#64748b">Cantidad · Total</div>';
            $html .= '<div style="font-weight:600">' . $lead->quantity . ' unidades · S/ ' . $total . '</div>';
            $html .= '</div>';

            $html .= '<div style="background:#fef3c7;border-radius:10px;padding:16px;margin:20px 0">';
            $html .= '<div style="font-size:13px;color:#92400e;margin-bottom:6px;font-weight:600">👤 Cliente</div>';
            $html .= '<div><strong>' . htmlspecialchars($lead->customer_name) . '</strong></div>';
            if ($lead->customer_phone) {
                $html .= '<div>📱 ' . htmlspecialchars($lead->customer_phone) . '</div>';
            }
            if ($lead->customer_email) {
                $html .= '<div>✉️ ' . htmlspecialchars($lead->customer_email) . '</div>';
            }
            if ($lead->message) {
                $html .= '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #fde68a;font-size:13px">💬 ' . htmlspecialchars($lead->message) . '</div>';
            }
            $html .= '</div>';

            $html .= '<a href="https://' . $tenantFqdn . '/orders" style="display:inline-block;background:#111;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600">';
            $html .= 'Ver pedido en mi panel →</a>';
            $html .= '<p style="font-size:12px;color:#9ca3af;margin-top:20px">Código externo: ' . substr($externalId, 0, 8) . '</p>';
            $html .= '</div></body></html>';

            $safeSubject = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n", "\t"], ' ', $subject)));
            $safeSubject = mb_substr($safeSubject, 0, 100);

            Mail::send([], [], function ($message) use ($to, $safeSubject, $html) {
                $message->to($to)->subject($safeSubject)->setBody($html, 'text/html');
            });

            Log::info('marketplace lead notification sent', [
                'lead_id' => $lead->id,
                'to'      => $to,
            ]);
        } catch (\Throwable $e) {
            Log::warning('marketplace lead notification failed', [
                'lead_id' => $lead->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
