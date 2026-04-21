<?php

namespace App\Services\System;

use App\Models\System\Client;
use App\Models\System\MarketplaceLead;
use App\Models\System\MarketplaceListing;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
}
