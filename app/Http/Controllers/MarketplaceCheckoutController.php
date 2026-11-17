<?php

namespace App\Http\Controllers;

use App\Models\System\MarketingContact;
use App\Models\System\MarketplaceOrder;
use App\Services\System\MarketplaceCartService;
use App\Services\System\MarketplaceCheckoutRules;
use App\Services\System\MarketplaceCheckoutService;
use App\Services\System\MarketplaceCheckoutShipping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
        private MarketplaceCheckoutShipping $shipping,
    ) {}

    public function show()
    {
        $this->cart->refresh();
        // Limpiar cupones huérfanos (tiendas cuyos items fueron removidos)
        $this->cart->pruneOrphanCoupons();
        $stores  = $this->cart->groupedByStore();
        $summary = $this->cart->summary();

        if ($stores->isEmpty()) {
            return redirect()->route('marketplace.cart')
                ->with('mp_message', 'Tu carrito está vacío.');
        }

        // Cupones ya aplicados (sobreviven entre cargas). El blade pre-llena
        // el input y marca como "aplicado" para que el cliente no tenga que
        // re-ingresar el código si navegó y volvió.
        $appliedCoupons = $this->cart->getCoupons();

        // Cupones de PLATAFORMA disponibles para el comprador logueado.
        // Se aplican AUTOMATICAMENTE al confirmar el pedido (el mejor por
        // tienda). Aqui los mostramos para que el comprador vea el ahorro
        // antes de confirmar.
        // Estructura: [hostname_id => Collection<['coupon','discount','assignment_id']>]
        $platformCoupons = collect();
        $platformDiscountTotal = 0.0;
        $mktUser = auth('marketplace')->user();
        if ($mktUser) {
            $couponSvc = app(\App\Services\Marketplace\MarketplaceCouponService::class);
            foreach ($stores as $store) {
                $hostnameId = (int) $store['hostname_id'];
                // Subtotal "neto" tras descuento de tenant si hay.
                $subtotal   = (float) $store['subtotal'];
                $tenantDisc = (float) ($appliedCoupons[$hostnameId]['discount'] ?? 0);
                $netoStore  = max(0, $subtotal - $tenantDisc);
                $available  = $couponSvc->availableForUser($mktUser, $hostnameId, $netoStore);
                if ($available->isNotEmpty()) {
                    $platformCoupons->put($hostnameId, $available);
                    // El que se aplicara: el mejor.
                    $best = $available->sortByDesc('discount')->first();
                    $platformDiscountTotal += (float) $best['discount'];
                }
            }
        }

        // InitiateCheckout. Se arma aqui y no en la vista para que el
        // content_id salga de AdsTracking y no pueda divergir del <g:id> del
        // feed. Sin event_id: este evento solo vive en el navegador.
        $adsCheckoutPayload = \App\Services\Marketplace\AdsTracking::payload(
            'initiate_checkout',
            $stores->flatMap(fn ($store) => collect($store['items'] ?? [])
                ->map(fn ($line) => \App\Services\Marketplace\AdsTracking::itemFromCartLine($line)))
                ->values()->all(),
            (float) ($summary['total'] ?? 0)
        );

        // Comprador con cuenta: nombre, teléfono, email y lo último que dio al
        // comprar (documento, dirección, distrito, modalidad). Sin cuenta, vacío.
        // `old()` manda sobre esto en la vista, así que un error de validación
        // no pisa lo que el comprador acaba de escribir.
        $prefill = [];
        if ($mktUser) {
            $profile = (array) ($mktUser->checkout_profile ?? []);
            $prefill = array_filter([
                'name'       => $mktUser->name,
                'phone'      => $profile['phone'] ?? $mktUser->phone,
                'email'      => $mktUser->email,
                'doc_type'   => $profile['doc_type'] ?? null,
                'doc_number' => $profile['doc_number'] ?? null,
                'address'    => $profile['address'] ?? null,
                'notes'      => $profile['notes'] ?? null,
                'department' => $profile['department'] ?? null,
                'province'   => $profile['province'] ?? null,
                'district'   => $profile['district'] ?? null,
                'mode'       => $profile['mode'] ?? null,
            ], fn ($v) => $v !== null && $v !== '');
        }

        return view('marketplace.checkout', compact(
            'stores', 'summary', 'appliedCoupons',
            'platformCoupons', 'platformDiscountTotal', 'adsCheckoutPayload', 'prefill'
        ) + ['paysOnline' => $this->checkoutService->paysOnline()]);
    }

    /**
     * «¿Cómo lo recibes?»: modalidades, costo y plazo de cada tienda del carrito
     * hacia el distrito elegido. Solo lee el carrito de la sesión: nunca revela
     * datos de tiendas que el comprador no tiene en su carrito.
     */
    public function quote(Request $request)
    {
        $data = $request->validate([
            'department' => 'nullable|string|max:80',
            'province'   => 'nullable|string|max:80',
            'district'   => 'nullable|string|max:80',
        ]);

        $stores = $this->cart->groupedByStore();
        if ($stores->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Tu carrito está vacío.'], 422);
        }

        $ubigeo = $this->shipping->resolveUbigeo(
            $data['department'] ?? null, $data['province'] ?? null, $data['district'] ?? null,
            $stores->pluck('hostname_id')->all()
        );
        $place = MarketplaceCheckoutShipping::placeLabel($data['department'] ?? null, $data['province'] ?? null, $data['district'] ?? null);

        return response()->json(['ok' => true, 'unresolved' => !$ubigeo && !empty($data['district'])]
            + $this->shipping->quote($stores, $ubigeo, $place))
            ->header('Cache-Control', 'no-store');
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

            // Persistir en sesión para que sobreviva navegación/recarga.
            // Re-validamos server-side igual al confirmar el pedido.
            $this->cart->applyCoupon($hostnameId, $code, $discount);

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

    /**
     * Quitar un cupón aplicado a una tienda. El cliente cambia de opinión o
     * cambió los items y ya no quiere ese código.
     */
    public function removeCoupon(int $hostnameId)
    {
        $this->cart->removeCoupon($hostnameId);
        return response()->json(['success' => true]);
    }

    public function store(Request $request)
    {
        // Honeypot: si rellenan `website` es bot.
        if (trim((string) $request->input('website')) !== '') {
            return redirect()->route('marketplace.cart');
        }

        $stores = $this->cart->groupedByStore();
        if ($stores->isEmpty()) {
            return redirect()->route('marketplace.cart')
                ->with('mp_message', 'Tu carrito está vacío.');
        }

        // Estos tres se rellenan dentro de after(): lo que NO se puede validar
        // con una regla suelta (documento según tipo, destino contra el catálogo,
        // modalidad ofrecida por cada tienda).
        $plans = [];
        $ubigeo = null;
        $docNumber = null;
        $phone = null;
        $address = null;

        $validator = Validator::make($request->all(), [
            'customer_name'        => 'required|string|min:3|max:180',
            'customer_doc_type'    => 'nullable|in:DNI,RUC,CE,Pasaporte',
            'customer_doc_number'  => 'nullable|string|max:30',
            'customer_phone'       => 'required|string|max:40',
            'customer_email'       => 'nullable|email|max:180',
            'delivery_address'     => 'nullable|string|max:500',
            'delivery_department'  => 'nullable|string|max:80',
            'delivery_province'    => 'nullable|string|max:80',
            'delivery_district'    => 'nullable|string|max:80',
            'delivery_ubigeo'      => 'nullable|string|max:6',
            'delivery_notes'       => 'nullable|string|max:1000',
            'accepts_marketing'    => 'nullable|boolean',
            'website'              => 'nullable|string',
            'ship'                 => 'nullable|array',
            // Cupones por tienda: { hostname_id => code }. La key es el id
            // numérico del hostname, value el código (el server lo revalida).
            'coupons'              => 'nullable|array',
            'coupons.*'            => 'nullable|string|max:60',
        ], [
            'customer_name.required' => 'Ingresa tu nombre completo o razón social.',
            'customer_name.min'      => 'Ingresa tu nombre completo o razón social.',
            'customer_phone.required'=> 'Ingresa tu celular o WhatsApp.',
            'customer_email.email'   => 'Revisa el formato del correo.',
        ]);

        $validator->after(function ($v) use ($request, $stores, &$plans, &$ubigeo, &$docNumber, &$phone, &$address) {
            $type = MarketplaceCheckoutRules::docType($request->input('customer_doc_type'));
            $docNumber = MarketplaceCheckoutRules::normalizeDocNumber($type, $request->input('customer_doc_number'));
            if ($e = MarketplaceCheckoutRules::docError($type, $request->input('customer_doc_number'))) {
                $v->errors()->add('customer_doc_number', $e);
            }

            $phone = MarketplaceCheckoutRules::normalizePhone($request->input('customer_phone'));
            if ($phone === null && trim((string) $request->input('customer_phone')) !== '') {
                $v->errors()->add('customer_phone', 'Debe ser un celular de 9 dígitos que empiece en 9 (con o sin +51).');
            }

            $dep  = $request->input('delivery_department');
            $prov = $request->input('delivery_province');
            $dist = $request->input('delivery_district');
            $ubigeo = $this->shipping->resolveUbigeo($dep, $prov, $dist, $stores->pluck('hostname_id')->all());
            $place = MarketplaceCheckoutShipping::placeLabel($dep, $prov, $dist);

            $sel = $this->shipping->resolveSelection($stores, (array) $request->input('ship', []), $ubigeo, $place);
            $plans = $sel['plans'];
            foreach ($sel['errors'] as $field => $msg) {
                $v->errors()->add($field, $msg);
            }

            // El destino solo es opcional cuando TODO se recoge en tienda.
            if (!$ubigeo && !$sel['all_pickup']) {
                $v->errors()->add('delivery_district', 'Elige tu distrito de la lista para saber a dónde va y cuánto cuesta el envío.');
            }

            $address = trim((string) $request->input('delivery_address'));
            if ($sel['any_delivery'] && mb_strlen($address) < 5) {
                $v->errors()->add('delivery_address', 'Ingresa la dirección de entrega (calle, número y distrito).');
            }
        });

        $data = $validator->validate();

        $data['customer_phone'] = $phone;
        $data['customer_doc_number'] = $docNumber !== '' ? $docNumber : null;
        $data['customer_doc_type'] = $data['customer_doc_number'] ? MarketplaceCheckoutRules::docType($data['customer_doc_type'] ?? null) : null;

        // Captura opt-in marketing — sólo si el comprador marcó el checkbox.
        // Sin este consent NO entra a marketing_contacts con flag activo.
        if (!empty($data['accepts_marketing'])) {
            $this->captureMarketingContact($data, $request);
        }

        $result = $this->checkoutService->process([
            'name'        => $data['customer_name'],
            'doc_type'    => $data['customer_doc_type'],
            'doc_number'  => $data['customer_doc_number'],
            'phone'       => $data['customer_phone'],
            'email'       => $data['customer_email']       ?? null,
            // La columna es NOT NULL: sin dirección se guarda el marcador que el
            // resto del ERP ya reconoce como «no hay dirección».
            'address'     => $address !== '' ? $address : 'Sin dirección',
            'department'  => $data['delivery_department']  ?? null,
            'province'    => $data['delivery_province']    ?? null,
            'district'    => $data['delivery_district']    ?? null,
            'ubigeo'      => $ubigeo,
            'notes'       => $data['delivery_notes']       ?? null,
            'shipping'    => $plans,
        ], $request);

        if (!($result['success'] ?? false)) {
            return back()
                ->withInput()
                ->withErrors($result['errors'] ?? ['Error procesando el pedido.']);
        }

        // Atribución: de qué campaña vino este pedido. Se escribe una sola
        // vez, al crearlo, con lo que el middleware mp.attribution guardó en
        // la primera visita. Si no hay origen (tráfico directo) no se escribe
        // nada en vez de rellenar con nulls.
        // Junto al origen se guarda lo que el comprador decidio sobre la
        // medicion: el webhook de la pasarela llega sin sesion y es el unico
        // sitio donde podra consultarlo.
        $attribution = \App\Services\Marketplace\AdsTracking::attributionColumns()
                     + \App\Services\Marketplace\AdsTracking::consentColumn();
        if (!empty($attribution)) {
            try {
                $result['order']->forceFill($attribution)->save();
            } catch (\Throwable $e) {
                // Un fallo de atribución no puede tumbar un pedido ya cobrado.
                \Log::warning('Atribución del pedido no guardada', [
                    'order' => $result['order']->order_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Purchase server-side. Sólo si el pedido NO pasa por MercadoPago: con
        // init_point todavía no hay pago, y medir ahí contaría como venta un
        // checkout abandonado en la pasarela. Ese caso lo cubre confirmation()
        // cuando payment_status llega a 'paid'.
        if (empty($result['init_point'])) {
            app(\App\Services\Marketplace\PurchaseConversion::class)
                ->sendOnce($result['order'], $request);
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
        //   2) un SuperAdmin autenticado (guard 'admin') — para soporte, o
        //   3) el marketplace_user logueado que es DUEÑO del pedido.
        // Los order_numbers MP-* son secuenciales y enumerables; sin esta guarda,
        // cualquiera con un número válido vería los datos personales del comprador.
        // BUG previo: usabamos $request->user() (guard default 'web'); al agregar
        // guard 'marketplace' eso devolvia ANY comprador logueado, permitiendole
        // ver ordenes ajenas.
        $order = MarketplaceOrder::query()
            ->with(['items.listing', 'tenantOrders'])
            ->where('order_number', $number)
            ->firstOrFail();

        $allowedNumbers = collect(session('mp_orders_placed', []));
        $isOwner   = $allowedNumbers->contains($number);
        $isStaff   = Auth::guard('admin')->check();
        $mktUser   = Auth::guard('marketplace')->user();
        $isMktOwner = $mktUser && $order->marketplace_user_id === $mktUser->id;

        if (!$isOwner && !$isStaff && !$isMktOwner) {
            abort(403);
        }

        $itemsByStore = $order->items->groupBy('hostname_id');
        $subOrders    = $order->tenantOrders->keyBy('hostname_id');

        // Pagado por MercadoPago: el Purchase no se pudo mandar en store()
        // porque entonces no había pago. Es idempotente, así que no dobla con
        // el de contra entrega.
        if (($order->payment_status ?? null) === 'paid') {
            app(\App\Services\Marketplace\PurchaseConversion::class)
                ->sendOnce($order, $request);
        }

        // Purchase del navegador. Lleva event_id sembrado con el número de
        // pedido — el mismo que calcula el envío server-side — para que la
        // plataforma deduplique en vez de contar la venta dos veces.
        $adsPurchasePayload = \App\Services\Marketplace\AdsTracking::payload(
            'purchase',
            $order->items->map(fn ($i) => [
                'content_id'   => \App\Services\Marketplace\AdsTracking::catalogId($i->listing_id),
                'content_name' => (string) $i->title,
                'quantity'     => (int) $i->quantity,
                'price'        => round((float) $i->unit_price, 2),
            ])->values()->all(),
            (float) $order->total,
            $order->order_number
        );

        // Un «paquete» por tienda, con lo que el comprador necesita saber:
        // quien lo envia (nombre, no el dominio), como contactarla y cuanto
        // cuesta el envio a SU destino. Los datos de la tienda salen de la
        // ficha publicada; si el producto ya no esta, de cualquier otra suya.
        $zona = (mb_strtoupper((string) $order->delivery_province) === 'LIMA'
                 || mb_strtoupper((string) $order->delivery_department) === 'CALLAO') ? 'lima' : 'province';
        $conDestino = (bool) $order->delivery_district;
        $packages = $itemsByStore->map(function ($items, $hostnameId) use ($subOrders, $zona, $conDestino) {
            $sub = $subOrders->get($hostnameId);
            $listing = $items->pluck('listing')->filter()->first()
                ?: \App\Models\System\MarketplaceListing::where('hostname_id', $hostnameId)->latest('updated_at')->first();
            $subtotal = (float) $items->sum('total');
            $discount = (float) ($sub?->discount_amount ?? 0);
            $wa = preg_replace('/\D+/', '', (string) ($listing->seller_whatsapp ?? ''));
            if ($wa !== '' && strlen($wa) === 9) {
                $wa = '51' . $wa;
            }

            return (object) [
                'name'     => html_entity_decode((string) ($listing?->seller_display ?: $items->first()->tenant_fqdn), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'logo'     => $listing->tenant_logo_url ?? null,
                'store'    => $listing?->subdomain ? route('marketplace.tenant', $listing->subdomain) : null,
                'whatsapp' => $wa ?: null,
                'items'    => $items,
                'status'   => $sub->status ?? 'pending',
                'coupon'   => $sub?->coupon_code,
                'discount' => $discount,
                'total'    => max(0, $subtotal - $discount),
                // Lo que el comprador ELIGIÓ y vio antes de pagar (guardado en el
                // subpedido). Solo los pedidos anteriores al selector, sin esa
                // foto, caen al cálculo con la política vigente.
                'ship'     => ($sub && $sub->shipping_mode)
                    ? [
                        'saved'   => true,
                        'mode'    => $sub->shipping_mode,
                        'label'   => $sub->shipping_label,
                        'text'    => $sub->shipping_pending && $sub->shipping_cost === null
                                        ? 'Te lo confirma la tienda'
                                        : ((float) $sub->shipping_cost > 0
                                            ? 'S/ ' . number_format((float) $sub->shipping_cost, 2) . ($sub->shipping_pending ? ' + por confirmar' : '')
                                            : ($sub->shipping_pending ? 'Por confirmar' : 'Gratis')),
                        'amount'  => $sub->shipping_cost,
                        'pending' => (bool) $sub->shipping_pending,
                        'free'    => !$sub->shipping_pending && (float) $sub->shipping_cost <= 0,
                        'eta'     => $sub->shipping_eta,
                        'office'  => $sub->agency_office_name,
                        'has_policy' => (bool) $sub->shipping_has_policy,
                    ]
                    : ($conDestino
                        ? \App\Services\Marketplace\ShippingSummaryText::forZone($listing->shipping ?? null, $zona, $subtotal)
                        : null),
            ];
        })->values();

        return view('marketplace.order_confirmation', compact(
            'order', 'itemsByStore', 'subOrders', 'adsPurchasePayload', 'packages'
        ));
    }
}
