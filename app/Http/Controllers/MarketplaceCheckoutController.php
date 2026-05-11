<?php

namespace App\Http\Controllers;

use App\Models\System\MarketingContact;
use App\Models\System\MarketplaceOrder;
use App\Services\System\MarketplaceCartService;
use App\Services\System\MarketplaceCheckoutService;
use Illuminate\Http\Request;

/**
 * Checkout multi-tienda del marketplace central. Se compone de:
 *   GET  /marketplace/checkout       → vista form
 *   POST /marketplace/checkout       → procesa, crea pedido, dispatcha a tenants
 *   GET  /marketplace/order/{number} → pantalla de confirmación
 */
class MarketplaceCheckoutController extends Controller
{
    public function __construct(
        private MarketplaceCartService $cart,
        private MarketplaceCheckoutService $checkoutService,
    ) {}

    public function show()
    {
        $this->cart->refresh();
        $stores  = $this->cart->groupedByStore();
        $summary = $this->cart->summary();

        if ($stores->isEmpty()) {
            return redirect()->route('marketplace.cart')
                ->with('mp_message', 'Tu carrito está vacío.');
        }

        return view('marketplace.checkout', compact('stores', 'summary'));
    }

    /**
     * Validar un cupón contra una tienda específica del cart.
     * El cliente envía { hostname_id, code }. Hacemos switch al tenant,
     * construimos el cart de esa tienda y corremos PromotionEngine en
     * preview (sin alterar contadores). Si el cupón es válido devolvemos
     * el descuento aplicable; si no, mensaje de error.
     *
     * Throttle: 20/min vía ruta (evita brute force de códigos).
     */
    public function validateCoupon(Request $request)
    {
        $data = $request->validate([
            'hostname_id' => 'required|integer',
            'code'        => 'required|string|max:60',
        ]);

        $code = strtoupper(trim($data['code']));
        $hostnameId = (int) $data['hostname_id'];

        // Buscar la tienda del cart
        $this->cart->refresh();
        $store = $this->cart->groupedByStore()->firstWhere('hostname_id', $hostnameId);
        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'Esta tienda ya no está en tu carrito.',
            ], 422);
        }

        // Resolver el tenant Website desde hostname_id
        $hostname = \Hyn\Tenancy\Models\Hostname::find($hostnameId);
        if (!$hostname || !$hostname->website) {
            return response()->json([
                'success' => false,
                'message' => 'Tienda no encontrada.',
            ], 404);
        }

        // Cart preview: armar el array de items para PromotionEngine en el
        // formato que espera (item_id, sale_unit_price, quantity, is_set,
        // etc.). El subtotal es la suma simple del store en sesión.
        $cartLines = collect($store['items'])->map(function ($line) {
            return [
                'item_id'         => (int) ($line['remote_item_id'] ?? 0),
                'sale_unit_price' => (float) $line['price'],
                'quantity'        => (int) $line['quantity'],
                'is_set'          => false,
            ];
        })->all();
        $subtotal = (float) $store['subtotal'];

        $tenancy = app(\Hyn\Tenancy\Environment::class);
        $originalTenant = $tenancy->tenant();

        try {
            $tenancy->tenant($hostname->website);

            // Verificar que el cupón exista en el tenant (mensaje más útil
            // que el genérico de PromotionEngine cuando el código no existe)
            $coupon = \App\Models\Tenant\Coupon::query()
                ->where('code', $code)
                ->where('active', true)
                ->first();
            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cupón no válido para esta tienda.',
                ], 422);
            }

            $result = \App\Services\Tenant\PromotionEngine::make($cartLines, $subtotal)
                ->withCoupon($code)
                ->withChannel(null, 'marketplace')
                ->calculate(false);

            $discount = (float) ($result['total_discount'] ?? 0);
            if ($discount <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'El cupón no aplica para los productos en tu carrito.',
                ], 422);
            }

            return response()->json([
                'success'        => true,
                'code'           => $code,
                'discount'       => round($discount, 2),
                'final_total'    => round(max(0, $subtotal - $discount), 2),
                'original_total' => round($subtotal, 2),
                'message'        => 'Cupón aplicado: -S/ ' . number_format($discount, 2),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            \Log::warning('[MarketplaceCheckout::validateCoupon] failed', [
                'hostname_id' => $hostnameId,
                'code'        => $code,
                'error'       => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'No se pudo validar el cupón. Intenta de nuevo.',
            ], 500);
        } finally {
            $tenancy->tenant($originalTenant ?: null);
        }
    }

    public function store(Request $request)
    {
        // Honeypot: si rellenan `website` es bot.
        if (trim((string) $request->input('website')) !== '') {
            return redirect()->route('marketplace.cart');
        }

        $data = $request->validate([
            'customer_name'        => 'required|string|max:180',
            'customer_doc_type'    => 'nullable|in:DNI,RUC,CE,Pasaporte',
            'customer_doc_number'  => 'nullable|string|max:20',
            'customer_phone'       => 'required|string|max:40',
            'customer_email'       => 'nullable|email|max:180',
            'delivery_address'     => 'required|string|max:500',
            'delivery_department'  => 'nullable|string|max:80',
            'delivery_province'    => 'nullable|string|max:80',
            'delivery_district'    => 'nullable|string|max:80',
            'delivery_notes'       => 'nullable|string|max:1000',
            'accepts_marketing'    => 'nullable|boolean',
            'website'              => 'nullable|string',
            // Cupones por tienda: { hostname_id => code }. La key es el id
            // numérico del hostname, value el código (el server lo revalida).
            'coupons'              => 'nullable|array',
            'coupons.*'            => 'nullable|string|max:60',
        ]);

        // Captura opt-in marketing — sólo si el comprador marcó el checkbox.
        // Sin este consent NO entra a marketing_contacts con flag activo.
        if (!empty($data['accepts_marketing'])) {
            $this->captureMarketingContact($data, $request);
        }

        $result = $this->checkoutService->process([
            'name'        => $data['customer_name'],
            'doc_type'    => $data['customer_doc_type']    ?? null,
            'doc_number'  => $data['customer_doc_number']  ?? null,
            'phone'       => $data['customer_phone'],
            'email'       => $data['customer_email']       ?? null,
            'address'     => $data['delivery_address'],
            'department'  => $data['delivery_department']  ?? null,
            'province'    => $data['delivery_province']    ?? null,
            'district'    => $data['delivery_district']    ?? null,
            'notes'       => $data['delivery_notes']       ?? null,
        ], $request);

        if (!($result['success'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors($result['errors'] ?? ['Error procesando el pedido.']);
        }

        // Persistimos los order_numbers que esta sesión pagó para que sólo
        // el comprador vea su propia confirmación (mitiga IDOR — los números
        // MP-* son secuenciales y enumerables).
        $placed = collect(session('mp_orders_placed', []));
        $placed = $placed->push($result['order']->order_number)->take(-50)->values()->all();
        session(['mp_orders_placed' => $placed]);

        // Si el checkout creó preferencia MercadoPago, redirigir al init_point
        // de MP en lugar de la pantalla de confirmación. La confirmación se
        // mostrará al cliente cuando MP redirija de vuelta tras el pago.
        if (!empty($result['init_point'])) {
            return redirect()->away($result['init_point']);
        }

        return redirect()->route('marketplace.order.confirmation', [
            'number' => $result['order']->order_number,
        ]);
    }

    /**
     * Endpoint de retorno desde MercadoPago tras pago (success/failure/pending).
     * MP redirige aquí con query params: status, payment_id, external_reference.
     *
     * No confiamos en estos params como verdad — el webhook es el source of
     * truth. Aquí solo mostramos UI apropiada al cliente y redirigimos a la
     * confirmación. El estado real de payment_status se actualiza vía webhook.
     */
    public function paymentReturn(Request $request)
    {
        $externalRef = $request->input('external_reference');
        if (!$externalRef) {
            return redirect()->route('marketplace.cart')
                ->with('mp_message', 'No se pudo identificar el pedido. Si pagaste, revisa tu correo en unos minutos.');
        }

        // Ahora la sesión que pagó SÍ puede ver la confirmación
        $placed = collect(session('mp_orders_placed', []));
        if (!$placed->contains($externalRef)) {
            $placed = $placed->push($externalRef)->take(-50)->values()->all();
            session(['mp_orders_placed' => $placed]);
        }

        return redirect()->route('marketplace.order.confirmation', [
            'number' => $externalRef,
        ])->with('mp_status', $request->input('status'));
    }

    /**
     * Webhook IPN de MercadoPago.
     * MP envía POST con body { type: 'payment', data: { id: '12345' } }.
     * Validamos vía API de MP el estado real del pago (NO confiar en body).
     */
    public function paymentWebhook(Request $request)
    {
        \Log::info('[MP Webhook] received', [
            'topic' => $request->input('type') ?? $request->input('topic'),
            'data'  => $request->input('data'),
            'ip'    => $request->ip(),
        ]);

        $type      = $request->input('type', $request->input('topic'));
        $paymentId = (string) ($request->input('data.id') ?? $request->input('id', ''));

        if ($type !== 'payment' || empty($paymentId)) {
            // MP también envía notificaciones de tipo 'merchant_order' que
            // ignoramos — solo procesamos 'payment'. Devolvemos 200 para
            // que MP no reintente.
            return response()->json(['ok' => true, 'ignored' => true], 200);
        }

        try {
            $mp = app(\App\Services\System\MercadoPagoService::class);
            $result = $mp->handleWebhook($paymentId);

            if (!($result['success'] ?? false)) {
                \Log::warning('[MP Webhook] handleWebhook failed', [
                    'payment_id' => $paymentId,
                    'message'    => $result['message'] ?? 'unknown',
                ]);
                return response()->json(['ok' => false, 'message' => $result['message'] ?? 'error'], 200);
            }

            return response()->json([
                'ok'     => true,
                'status' => $result['status'] ?? null,
                'order'  => $result['order']->order_number ?? null,
            ], 200);
        } catch (\Throwable $e) {
            \Log::error('[MP Webhook] exception', [
                'payment_id' => $paymentId,
                'error'      => $e->getMessage(),
            ]);
            // Devolvemos 200 para que MP NO reintente indefinidamente
            // (los errores de configuración nuestros no se resuelven con retries).
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 200);
        }
    }

    /**
     * Permite reintentar el pago de una orden marketplace si payment_status
     * sigue 'unpaid'. Útil cuando el cliente cerró la pestaña antes de pagar.
     */
    public function paymentRetry(string $number)
    {
        $allowedNumbers = collect(session('mp_orders_placed', []));
        if (!$allowedNumbers->contains($number)) {
            abort(403);
        }

        $order = MarketplaceOrder::where('order_number', $number)->firstOrFail();

        if ($order->payment_status === 'paid') {
            return redirect()->route('marketplace.order.confirmation', ['number' => $number])
                ->with('mp_message', 'Esta orden ya está pagada.');
        }

        // Si tiene init_point vigente, reusar
        if (!empty($order->mp_init_point)) {
            return redirect()->away($order->mp_init_point);
        }

        // Si no, regenerar la preferencia
        try {
            $mp = app(\App\Services\System\MercadoPagoService::class);
            $result = $mp->createPreferenceForOrder($order);
            if (!($result['success'] ?? false)) {
                return redirect()->route('marketplace.order.confirmation', ['number' => $number])
                    ->with('mp_message', 'No se pudo iniciar el pago. Contacta soporte.');
            }
            return redirect()->away($result['init_point']);
        } catch (\Throwable $e) {
            return redirect()->route('marketplace.order.confirmation', ['number' => $number])
                ->with('mp_message', 'Error iniciando pago: ' . $e->getMessage());
        }
    }

    /**
     * Inserta o actualiza el contacto en marketing_contacts con consent=true.
     * Idempotente: si ya existe por phone/email, actualiza datos pero respeta
     * un opted_out previo (no lo reactiva sin acción explícita del contacto).
     */
    private function captureMarketingContact(array $data, Request $request): void
    {
        try {
            $phone = $data['customer_phone']  ?? null;
            $email = $data['customer_email']  ?? null;

            if (!$phone && !$email) {
                return;
            }

            $existing = MarketingContact::query()
                ->when($phone, fn ($q) => $q->orWhere('phone', $phone))
                ->when($email, fn ($q) => $q->orWhere('email', $email))
                ->first();

            if ($existing) {
                if ($existing->opted_out) {
                    // Respetar la decisión previa del contacto — no reactivar
                    return;
                }
                $existing->update([
                    'name'              => $existing->name ?: ($data['customer_name'] ?? $existing->name),
                    'phone'             => $phone ?: $existing->phone,
                    'email'             => $email ?: $existing->email,
                    'consent_marketing' => true,
                    'consent_at'        => $existing->consent_at ?: now(),
                    'consent_source'    => $existing->consent_source ?: 'checkout',
                ]);
                return;
            }

            MarketingContact::create([
                'name'              => $data['customer_name'] ?? null,
                'phone'             => $phone,
                'email'             => $email,
                'consent_marketing' => true,
                'consent_source'    => 'checkout',
                'source'            => 'marketplace_checkout',
            ]);
        } catch (\Throwable $e) {
            // Capturar contact NO debe romper el checkout
            \Log::warning('captureMarketingContact failed', ['error' => $e->getMessage()]);
        }
    }

    public function confirmation(string $number, Request $request)
    {
        // Mitigación IDOR: sólo deja ver la confirmación a:
        //   1) la sesión que generó el pedido (vía store() arriba), o
        //   2) un usuario autenticado del sistema (admin/SuperAdmin) — para soporte.
        // Los order_numbers MP-* son secuenciales y enumerables; sin esta guarda,
        // cualquiera con un número válido vería los datos personales del comprador.
        $allowedNumbers = collect(session('mp_orders_placed', []));
        $isOwner        = $allowedNumbers->contains($number);
        $isStaff        = $request->user() !== null;

        if (!$isOwner && !$isStaff) {
            abort(403);
        }

        $order = MarketplaceOrder::query()
            ->with(['items', 'tenantOrders'])
            ->where('order_number', $number)
            ->firstOrFail();

        $itemsByStore = $order->items->groupBy('hostname_id');
        $subOrders    = $order->tenantOrders->keyBy('hostname_id');

        return view('marketplace.order_confirmation', compact('order', 'itemsByStore', 'subOrders'));
    }
}
