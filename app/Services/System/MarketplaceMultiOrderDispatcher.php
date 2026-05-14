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
                // Si el cliente aplicó cupón en checkout del marketplace, el
                // descuento ya se calculó en TenantMarketplaceOrder.discount_amount.
                // Aquí lo propagamos al Order del tenant para que su contabilidad,
                // métricas y comprobante reflejen el descuento real.
                'total'             => round(max(0, $orderSubtotal - (float) ($sub->discount_amount ?? 0)), 2),
                'subtotal'          => $orderSubtotal,
                'total_discount'    => round((float) ($sub->discount_amount ?? 0), 2),
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

            // Si se usó cupón, incrementar used_count en el tenant. PromotionEngine
            // en preview no toca contadores; lo hacemos aquí cuando el subpedido se
            // confirma. Best-effort: si el cupón se borró entre checkout y dispatch,
            // no hay nada que incrementar y seguimos sin romper el flujo.
            if (!empty($sub->coupon_code)) {
                try {
                    DB::connection('tenant')->table('coupons')
                        ->where('code', $sub->coupon_code)
                        ->increment('used_count');
                } catch (\Throwable $e) {
                    Log::warning('marketplace coupon used_count increment failed', [
                        'tenant' => $client->hostname->fqdn,
                        'code'   => $sub->coupon_code,
                        'error'  => $e->getMessage(),
                    ]);
                }
            }

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

            // CRITICO: las notificaciones (notifyTenant + notifyTenantWhatsApp)
            // usan resolveTenantAdminContact() que lee de tenant.users. Si
            // cambiamos a null ANTES, la consulta cross-DB falla y el email
            // se manda al alias generico admin@ebaemy.com (NO al seller real).
            //
            // Disparamos las notificaciones AUN dentro del contexto tenant,
            // y solo despues volvemos a sistema. La sub_update y dispatched_at
            // pueden esperar porque usan UsesSystemConnection (tabla system).

            $this->notifyTenant($client, $order, $sub, $items, $orderSubtotal);
            $this->notifyTenantWhatsApp($client, $order, $items, $orderSubtotal);

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
     * Notifica al seller por WhatsApp del nuevo pedido marketplace usando el
     * número de WhatsApp registrado en clients.phone_ws (system DB). Se
     * envía DESDE el WhatsApp del system (ebaemy.com), no del tenant —
     * así funciona incluso si el seller aún no configuró su propio QR API
     * o Meta Cloud en su panel.
     *
     * Internamente ya se manda otra notif via WhatsAppService del tenant
     * (línea 222, notifyAdminMarketplaceOrder) si el tenant lo configuró.
     * Si ambos están configurados el seller recibe 2 mensajes — preferible
     * a no recibir ninguno.
     */
    private function notifyTenantWhatsApp(
        Client $client,
        MarketplaceOrder $order,
        Collection $items,
        float $subtotal
    ): void {
        // 1) Intentar con clients.phone_ws (flujo seller_applications nuevo).
        $phone = preg_replace('/\D+/', '', (string) ($client->phone_ws ?? ''));

        // 2) Fallback: si el client no tiene phone_ws (caso típico de tenants
        //    creados manualmente antes de 2026-04), pegamos a tenant.users
        //    type='admin' y tomamos su telephone/phone. Asi el WhatsApp
        //    llega al seller real sin tener que rellenar clients.phone_ws.
        if (empty($phone) || mb_strlen($phone) < 9) {
            $admin = $this->resolveTenantAdminContact();
            if (!empty($admin['phone'])) {
                $phone = preg_replace('/\D+/', '', (string) $admin['phone']);
            }
        }

        if (empty($phone) || mb_strlen($phone) < 9) {
            Log::info('marketplace WA: tenant sin telefono (phone_ws ni admin user), skip', [
                'order' => $order->order_number,
                'client_id' => $client->id,
            ]);
            return;
        }

        try {
            $tenantFqdn = $client->hostname->fqdn ?? '';
            $itemsCount = $items->count();
            $titleSummary = $itemsCount === 1
                ? mb_substr((string) $items->first()->title, 0, 60)
                : $itemsCount . ' productos';

            $msg  = "🛍️ *Nuevo pedido marketplace* {$order->order_number}\n\n";
            $msg .= "🏪 Tienda: {$tenantFqdn}\n";
            $msg .= "🛒 {$titleSummary}\n";
            $msg .= "💰 Total: *S/ " . number_format($subtotal, 2) . "*\n\n";
            $msg .= "👤 Cliente: *{$order->customer_name}*\n";
            $msg .= "📱 " . $order->customer_phone . "\n";
            if ($order->customer_email) $msg .= "✉️ {$order->customer_email}\n";
            if ($order->delivery_address) {
                $addr = $order->delivery_address;
                if ($order->delivery_district) $addr .= ' — ' . $order->delivery_district;
                $msg .= "📍 " . mb_substr($addr, 0, 120) . "\n";
            }
            $msg .= "\nRevisa tu panel: https://{$tenantFqdn}/orders";

            dispatch(\App\Jobs\SendWhatsAppMessage::text($phone, $msg));

            Log::info('marketplace WA tenant notif dispatched', [
                'order' => $order->order_number,
                'to'    => $phone,
            ]);
        } catch (\Throwable $e) {
            Log::warning('marketplace WA tenant notification failed', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyTenant(
        Client $client,
        MarketplaceOrder $order,
        TenantMarketplaceOrder $sub,
        Collection $items,
        float $subtotal
    ): void {
        // Resolucion de destinatario con logging por paso para que sea
        // facil de auditar via storage/logs/laravel.log si algo no llega.
        $sources = [];

        $toEmail = $client->contact_email ?: null;
        if ($toEmail) $sources[] = "contact_email=$toEmail";

        if (empty($toEmail)) {
            $toEmail = $client->email ?: null;
            if ($toEmail) $sources[] = "email=$toEmail";
        }

        $isGenericAdminEmail = !empty($toEmail) && (
            stripos($toEmail, 'admin@ebaemy.com') !== false ||
            $toEmail === 'admin@' . ($client->hostname->fqdn ?? '')
        );

        // Fallback: buscar admin real del tenant.users si client.email es
        // generico o vacio. Requiere estar en contexto tenant — el caller
        // dispatchTenantSubOrder garantiza eso desde commit posterior.
        if (empty($toEmail) || $isGenericAdminEmail) {
            $admin = $this->resolveTenantAdminContact();
            if (!empty($admin['email'])) {
                $sources[] = "tenant_admin_user=" . $admin['email'];
                // Solo reemplazar si NO es tambien admin@ebaemy.com
                $adminIsGeneric = stripos($admin['email'], 'admin@ebaemy.com') !== false;
                if (!$adminIsGeneric) {
                    $toEmail = $admin['email'];
                }
            } else {
                $sources[] = "tenant_admin_user=null";
            }
        }

        Log::info('marketplace notifyTenant resolved recipient', [
            'order'        => $order->order_number,
            'client_id'    => $client->id,
            'tenant_fqdn'  => $client->hostname->fqdn ?? null,
            'final_email'  => $toEmail,
            'is_generic'   => $isGenericAdminEmail,
            'sources_tried' => $sources,
        ]);

        if (empty($toEmail)) {
            Log::warning('marketplace multi-order tenant: sin email destinatario', [
                'order' => $order->order_number,
                'tenant_fqdn' => $client->hostname->fqdn ?? '?',
                'client_id' => $client->id,
            ]);
            return;
        }

        try {
            $tenantFqdn = $client->hostname->fqdn ?? '';

            // Aseguramos config SMTP del sistema (lee mail_* de configurations
            // y los setea en config('mail.*')). Si .env ya tiene MAIL_HOST
            // valido este no-op queda silente — no hace daño.
            try { \App\Models\System\Configuration::setConfigSmtpMail(); } catch (\Throwable $_) {}

            // Patron Mailable estandar — antes usabamos Mail::send([], [], closure)
            // que en algunas configs de driver no enviaba el body. Misma forma que
            // MarketplaceOrderConfirmationMail (al comprador) que si funciona.
            Mail::to($toEmail)->send(
                new \App\Mail\MarketplaceTenantOrderMail($order, $sub, $items, $subtotal, $tenantFqdn)
            );

            Log::info('marketplace multi-order tenant notif enviada', [
                'order' => $order->order_number,
                'to'    => $toEmail,
                'tenant' => $tenantFqdn,
            ]);
        } catch (\Throwable $e) {
            Log::warning('marketplace multi-order tenant notification failed', [
                'order' => $order->order_number,
                'to'    => $toEmail ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lee el contacto del admin del tenant actual (debe haberse cambiado
     * el contexto via Tenancy\Environment->tenant() antes de llamar).
     *
     * Sirve de fallback cuando clients.phone_ws o clients.contact_email
     * estan vacios/genericos — caso de tenants creados manualmente antes
     * del flujo seller_applications.
     *
     * Devuelve ['email' => ?, 'phone' => ?] (cualquiera puede ser null).
     */
    private function resolveTenantAdminContact(): array
    {
        try {
            // Datos REALES de contacto del seller — el tenant los configura
            // en /ecommerce/configuration → 'Informacion de Contacto'. Estos
            // SI son confiables (los pone el seller, no son alias genericos).
            // Tabla tenant.configurations, campos:
            //   - information_contact_email  (email del seller)
            //   - phone_whatsapp             (WhatsApp del seller)
            // Fallback secundario: information_contact_phone (telefono fijo).
            $cfg = DB::connection('tenant')->table('configurations')
                ->where('id', 1)
                ->first(['information_contact_email', 'phone_whatsapp', 'information_contact_phone']);

            if (!$cfg) {
                return ['email' => null, 'phone' => null];
            }

            return [
                'email' => $cfg->information_contact_email ?: null,
                'phone' => $cfg->phone_whatsapp
                        ?: ($cfg->information_contact_phone ?: null),
            ];
        } catch (\Throwable $e) {
            Log::info('resolveTenantAdminContact failed', ['error' => $e->getMessage()]);
            return ['email' => null, 'phone' => null];
        }
    }
}
