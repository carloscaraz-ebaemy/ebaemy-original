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
     * ¿MercadoPago está activo? Si MP_ACCESS_TOKEN está vacío, caemos al
     * flujo legacy (dispatch inmediato sin pasarela). Esto evita romper
     * tenants que aún no configuraron credenciales MP.
     */
    private function mercadopagoEnabled(): bool
    {
        return !empty(config('services.mercadopago.access_token'));
    }

    /**
     * Para cada tienda del cart, valida el cupón provisto (si hay) contra
     * el tenant correspondiente vía PromotionEngine en modo preview (sin
     * incrementar contadores). Devuelve un array {hostname_id => {code,
     * discount}} con los cupones aplicables.
     *
     * Re-valida server-side aunque el AJAX ya haya devuelto OK — el cliente
     * puede manipular el form, así que no podemos confiar.
     */
    private function resolveCouponsForStores($stores, array $couponsInput): array
    {
        $resolved = [];
        if (empty($couponsInput)) return $resolved;

        $tenancy = app(\Hyn\Tenancy\Environment::class);
        $originalTenant = $tenancy->tenant();

        foreach ($stores as $store) {
            $hostId = $store['hostname_id'];
            $code = trim((string) ($couponsInput[$hostId] ?? ''));
            if ($code === '') continue;
            $code = strtoupper($code);

            $hostname = \Hyn\Tenancy\Models\Hostname::find($hostId);
            if (!$hostname || !$hostname->website) continue;

            try {
                $tenancy->tenant($hostname->website);

                $cartLines = collect($store['items'])->map(function ($line) {
                    return [
                        'item_id'         => (int) ($line['remote_item_id'] ?? 0),
                        'sale_unit_price' => (float) $line['price'],
                        'quantity'        => (int) $line['quantity'],
                        'is_set'          => false,
                    ];
                })->all();

                $result = \App\Services\Tenant\PromotionEngine::make($cartLines, (float) $store['subtotal'])
                    ->withCoupon($code)
                    ->withChannel(null, 'marketplace')
                    ->calculate(false);

                $discount = (float) ($result['total_discount'] ?? 0);
                if ($discount > 0) {
                    $resolved[$hostId] = ['code' => $code, 'discount' => $discount];
                }
            } catch (\Throwable $e) {
                Log::warning('[MarketplaceCheckout] coupon validation failed', [
                    'hostname_id' => $hostId,
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);
                // Silenciamos: si falla la validación, el cupón simplemente no aplica
            }
        }

        $tenancy->tenant($originalTenant ?: null);
        return $resolved;
    }

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

        // Cupones enviados en el form: { hostname_id => code }. Los re-validamos
        // server-side antes de guardar (no confiar en lo que mostró el AJAX).
        // Resultado por tienda: { hostname_id => ['code', 'discount'] }.
        $couponsInput = (array) ($request?->input('coupons') ?? []);
        $couponsResolved = $this->resolveCouponsForStores($stores, $couponsInput);

        // Cupones DE PLATAFORMA del comprador logueado. Se aplican
        // automaticamente (mejor descuento por tienda). Si el subtotal
        // queda bajo el min_subtotal del cupon o se invalida, el service
        // lo filtra. Estructura: [hostname_id => ['coupon', 'discount',
        // 'assignment_id']].
        $platformResolved = [];
        $mktUser = \Illuminate\Support\Facades\Auth::guard('marketplace')->user();
        if ($mktUser) {
            $couponSvc = app(\App\Services\Marketplace\MarketplaceCouponService::class);
            foreach ($stores as $store) {
                $hostnameId = (int) $store['hostname_id'];
                $subtotal   = (float) $store['subtotal'];
                $available = $couponSvc->availableForUser($mktUser, $hostnameId, $subtotal);
                if ($available->isNotEmpty()) {
                    // El mejor (mayor descuento) en esta tienda.
                    $best = $available->sortByDesc('discount')->first();
                    $platformResolved[$hostnameId] = $best;
                }
            }
        }

        try {
            $order = DB::connection('system')->transaction(function () use ($customer, $stores, $request, $couponsResolved, $platformResolved) {
                $totalAll      = 0.0;
                $itemsAll      = 0;
                $discountTotal = 0.0;

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

                    // Aplicar cupón del tenant si aplica a esta tienda
                    $couponData = $couponsResolved[$store['hostname_id']] ?? null;
                    $storeDiscount = (float) ($couponData['discount'] ?? 0);
                    if ($storeDiscount > $storeSubtotal) $storeDiscount = $storeSubtotal;

                    // Aplicar cupón de plataforma (asignado al user) si lo hay.
                    // Se calcula sobre el subtotal RESTANTE despues del tenant
                    // coupon — evita doble descuento sobre el mismo monto y
                    // mantiene el comportamiento previsible.
                    $platCouponData = $platformResolved[$store['hostname_id']] ?? null;
                    $platDiscount = 0.0;
                    if ($platCouponData) {
                        $remainingAfterTenant = max(0, $storeSubtotal - $storeDiscount);
                        $platDiscount = $platCouponData['coupon']->discountFor($remainingAfterTenant);
                        if ($platDiscount > $remainingAfterTenant) $platDiscount = $remainingAfterTenant;
                    }

                    $discountTotal += $storeDiscount + $platDiscount;

                    TenantMarketplaceOrder::create([
                        'marketplace_order_id'           => $order->id,
                        'hostname_id'                    => $store['hostname_id'],
                        'tenant_fqdn'                    => $store['tenant_fqdn'],
                        'subtotal'                       => round($storeSubtotal, 2),
                        'coupon_code'                    => $couponData['code'] ?? null,
                        'discount_amount'                => round($storeDiscount, 2),
                        'platform_coupon_code'           => $platCouponData ? $platCouponData['coupon']->code : null,
                        'platform_discount_amount'       => round($platDiscount, 2),
                        'platform_coupon_assignment_id'  => $platCouponData ? $platCouponData['assignment_id'] : null,
                        'item_count'                     => $storeCount,
                        'status'                         => TenantMarketplaceOrder::STATUS_PENDING,
                        'retry_count'                    => 0,
                    ]);
                }

                $order->update([
                    'subtotal'       => round($totalAll, 2),
                    'discount_total' => round($discountTotal, 2),
                    'total'          => round(max(0, $totalAll - $discountTotal), 2),
                    'items_count'    => $itemsAll,
                ]);

                return $order;
            });

            // ── Pasarela MercadoPago ──────────────────────────────────────
            // Si MP está activo, creamos la preferencia y devolvemos el
            // init_point para que el comprador sea redirigido al checkout MP.
            // El dispatch a tenants queda diferido hasta que el webhook
            // confirme el pago (MercadoPagoService::handleWebhook).
            //
            // Si MP NO está configurado (legacy), dispatchamos inmediato
            // como antes — preserva compatibilidad con instalaciones que
            // aún cobran fuera del sistema (transferencia, contraentrega).
            $mpResult = null;
            if ($this->mercadopagoEnabled()) {
                try {
                    $mp = app(MercadoPagoService::class);
                    $mpResult = $mp->createPreferenceForOrder($order);
                    if (!($mpResult['success'] ?? false)) {
                        Log::warning('Marketplace checkout: MP failed, falling back to manual', [
                            'order' => $order->order_number,
                            'error' => $mpResult['error'] ?? 'unknown',
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('MercadoPago init fail, falling back to manual', [
                        'order' => $order->order_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Si MP NO disponible o falló, dispatch inmediato (modo legacy).
            // Si MP exitoso, NO dispatchamos aún — espera el webhook.
            $dispatchResult = null;
            if (!$mpResult || !($mpResult['success'] ?? false)) {
                $dispatchResult = $this->dispatcher->dispatchOrder($order);
            }

            // Limpiar carrito SIEMPRE (el pedido ya quedó persistido —
            // si algún subpedido falló, el SuperAdmin/tenant puede atenderlo).
            $this->cart->clear();

            // Redeem de cupones de plataforma usados en este pedido.
            // Best-effort: si falla la fila, no afecta al pedido (el cupon
            // queda visible al user, podemos limpiar a mano si pasa).
            // Si el pago se cancela despues (MP webhook), liberar el cupon
            // es responsabilidad de fase posterior — por ahora una vez
            // marcado used se considera consumido.
            try {
                $platSvc = app(\App\Services\Marketplace\MarketplaceCouponService::class);
                foreach (\App\Models\System\TenantMarketplaceOrder::where('marketplace_order_id', $order->id)
                            ->whereNotNull('platform_coupon_assignment_id')
                            ->get() as $sub) {
                    $platSvc->redeem(
                        (int) $sub->platform_coupon_assignment_id,
                        (int) $sub->hostname_id,
                        (int) ($sub->tenant_order_id ?: 0),
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Platform coupon redeem failed', ['err' => $e->getMessage(), 'order' => $order->id]);
            }

            $order->refresh();

            // Email de confirmación al comprador. Best-effort — el pedido
            // ya está persistido (y dispatchado o pendiente de pago).
            $this->safeNotifyCustomer($order);

            return [
                'success'    => true,
                'order'      => $order,
                'dispatch'   => $dispatchResult,
                'mp'         => $mpResult,
                'init_point' => $mpResult['init_point'] ?? null,
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
