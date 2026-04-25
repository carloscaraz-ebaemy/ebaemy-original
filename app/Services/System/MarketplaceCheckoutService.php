<?php

namespace App\Services\System;

use App\Mail\MarketplaceOrderConfirmationMail;
use App\Models\System\Configuration as SystemConfiguration;
use App\Models\System\MarketplaceListing;
use App\Models\System\MarketplaceOrder;
use App\Models\System\MarketplaceOrderItem;
use App\Models\System\TenantMarketplaceOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Orquesta el checkout multi-tienda del marketplace central:
 *
 *   1. Validar stock contra listings publicados
 *   2. Crear MarketplaceOrder padre + MarketplaceOrderItem snapshots + TenantMarketplaceOrder por seller
 *      (todo en una transacción del sistema)
 *   3. Despachar al tenant correspondiente vía MarketplaceMultiOrderDispatcher
 *   4. Recomputar status del padre según resultado de cada hijo
 *   5. Limpiar carrito y notificar al comprador (resumen general)
 *
 * El paso 3 NO está dentro de la transacción del paso 2: si un dispatch al
 * tenant falla, el subpedido queda con status=failed pero el padre persiste
 * para que se pueda reintentar luego desde el panel SuperAdmin.
 */
class MarketplaceCheckoutService
{
    public function __construct(
        private MarketplaceCartService $cart,
        private MarketplaceMultiOrderDispatcher $dispatcher,
    ) {}

    /**
     * Crea el pedido + subpedidos + dispara dispatch.
     *
     * @param array $customer  keys: name, doc_type, doc_number, phone, email,
     *                                address, department, province, district, notes
     * @return array{success: bool, order?: MarketplaceOrder, errors?: array, message?: string}
     */
    public function process(array $customer, ?Request $request = null): array
    {
        $stockErrors = $this->cart->validateStock();
        if (!empty($stockErrors)) {
            return ['success' => false, 'errors' => $stockErrors];
        }

        $stores = $this->cart->groupedByStore();
        if ($stores->isEmpty()) {
            return ['success' => false, 'errors' => ['Tu carrito está vacío.']];
        }

        try {
            $order = DB::connection('system')->transaction(function () use ($customer, $stores, $request) {
                $totalAll = 0.0;
                $itemsAll = 0;

                $order = MarketplaceOrder::create([
                    'order_number'         => MarketplaceOrder::generateOrderNumber(),
                    'customer_name'        => $customer['name'],
                    'customer_doc_type'    => $customer['doc_type']    ?? null,
                    'customer_doc_number'  => $customer['doc_number']  ?? null,
                    'customer_phone'       => $customer['phone'],
                    'customer_email'       => $customer['email']       ?? null,
                    'delivery_address'     => $customer['address'],
                    'delivery_department'  => $customer['department']  ?? null,
                    'delivery_province'    => $customer['province']    ?? null,
                    'delivery_district'    => $customer['district']    ?? null,
                    'delivery_notes'       => $customer['notes']       ?? null,
                    'status'               => MarketplaceOrder::STATUS_PENDING,
                    'payment_status'       => 'unpaid',
                    'source'               => 'web',
                    'session_token'        => Str::random(48),
                    'source_ip'            => $request?->ip(),
                    'source_ua'            => Str::limit((string) $request?->userAgent(), 250, ''),
                    'subtotal'             => 0,
                    'total'                => 0,
                    'items_count'          => 0,
                    'stores_count'         => $stores->count(),
                ]);

                foreach ($stores as $store) {
                    $storeSubtotal = 0.0;
                    $storeCount    = 0;

                    foreach ($store['items'] as $line) {
                        $unit  = (float) $line['price'];
                        $qty   = (int) $line['quantity'];
                        $total = round($unit * $qty, 2);
                        $storeSubtotal += $total;
                        $storeCount    += $qty;
                        $totalAll      += $total;
                        $itemsAll      += $qty;

                        MarketplaceOrderItem::create([
                            'marketplace_order_id' => $order->id,
                            'listing_id'           => $line['listing_id'],
                            'hostname_id'          => $line['hostname_id'],
                            'tenant_fqdn'          => $line['tenant_fqdn'],
                            'remote_item_id'       => $line['remote_item_id'],
                            'title'                => $line['title'],
                            'slug'                 => $line['slug']      ?? null,
                            'image_url'            => $line['image_url'] ?? null,
                            'unit_price'           => $unit,
                            'quantity'             => $qty,
                            'total'                => $total,
                        ]);
                    }

                    TenantMarketplaceOrder::create([
                        'marketplace_order_id' => $order->id,
                        'hostname_id'          => $store['hostname_id'],
                        'tenant_fqdn'          => $store['tenant_fqdn'],
                        'subtotal'             => round($storeSubtotal, 2),
                        'item_count'           => $storeCount,
                        'status'               => TenantMarketplaceOrder::STATUS_PENDING,
                        'retry_count'          => 0,
                    ]);
                }

                $order->update([
                    'subtotal'    => round($totalAll, 2),
                    'total'       => round($totalAll, 2),
                    'items_count' => $itemsAll,
                ]);

                return $order;
            });

            // Dispatch FUERA de la transacción del padre. Si un tenant falla,
            // el padre queda persistido y se puede reintentar luego.
            $dispatchResult = $this->dispatcher->dispatchOrder($order);

            // Limpiar carrito SIEMPRE (el pedido ya quedó persistido —
            // si algún subpedido falló, el SuperAdmin/tenant puede atenderlo).
            $this->cart->clear();

            $order->refresh();

            // Email de confirmación al comprador. Best-effort — el pedido
            // ya está persistido y dispatchado, fallar el mail no es crítico.
            $this->safeNotifyCustomer($order);

            return [
                'success'  => true,
                'order'    => $order,
                'dispatch' => $dispatchResult,
            ];
        } catch (\Throwable $e) {
            Log::error('MarketplaceCheckoutService::process failed', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'errors'  => ['Ocurrió un error procesando tu pedido. Vuelve a intentarlo.'],
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Envía confirmación por email al comprador. Si no dejó email o el
     * envío falla, solo se loggea — no rompe el flujo de checkout.
     */
    private function safeNotifyCustomer(MarketplaceOrder $order): void
    {
        if (empty($order->customer_email)) {
            return;
        }

        try {
            SystemConfiguration::setConfigSmtpMail();
            Mail::to($order->customer_email)
                ->send(new MarketplaceOrderConfirmationMail($order));
        } catch (\Throwable $e) {
            Log::warning('marketplace customer confirmation mail failed', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
