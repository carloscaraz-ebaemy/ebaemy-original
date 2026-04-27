<?php
namespace Modules\Ecommerce\Http\Controllers;


use App\Models\Tenant\Configuration;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Culqi\Culqi;
use Culqi\CulqiException;
use stdClass;
use Illuminate\Support\Facades\Auth;
use App\Models\Tenant\Order;
use Illuminate\Support\Str;
use App\Models\Tenant\Person;
use Exception;
use App\Models\Tenant\ConfigurationEcommerce;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemWarehouse;
use App\Jobs\CapturePaymentJob;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\ItemVariantWarehouse;
use App\Models\Tenant\SalesChannel;
use App\Services\Tenant\PromotionEngine;




class CulqiController extends Controller
{

    public function __construct()
    {
        // $this->middleware('input.request:document,web', ['only' => ['store']]);
    }

    public function index()
    {

    }

    public function payment(Request $request)
    {
      try{

        $input = json_decode($request->getContent(), true) ?: $request->all();
        $customerRaw = $input['customer'] ?? $request->customer ?? [];
        if (is_string($customerRaw)) {
            $decoded = json_decode($customerRaw, true);
            $customer = is_array($decoded) ? $decoded : [];
        } elseif (is_object($customerRaw)) {
            $customer = (array) $customerRaw;
        } else {
            $customer = (array) $customerRaw;
        }

        $deliveryType = strtolower((string)($input['delivery_type'] ?? $request->input('delivery_type', 'delivery')));
        $isPickup = $deliveryType === 'pickup';
        if ($isPickup) {
            $customer['direccion'] = 'Recojo en tienda';
        }

        $validator = Validator::make($customer, [
            'telefono' => 'required|numeric',
            'direccion' => $isPickup ? 'nullable|string' : 'required|string',
            'codigo_tipo_documento_identidad' => 'required|numeric',
            'numero_documento' => 'required|numeric',
            'identity_document_type_id' => 'required|numeric'
        ]);

        if ($validator->fails()) {
          return response()->json($validator->errors(), 422);
        }


        $user = auth()->user();
        $configuration = ConfigurationEcommerce::firstCached();

        $itemsRaw = $input['items'] ?? $request->items ?? [];
        if (is_string($itemsRaw)) {
            $culqiItems = json_decode($itemsRaw, true) ?? [];
        } else {
            $culqiItems = is_array($itemsRaw) ? $itemsRaw : [];
        }

        // ── VERIFICACIÓN SERVER-SIDE DE PRECIOS ──────────────────────────────
        // Recalcular total desde BD — ignorar completamente los precios enviados
        // por el cliente para evitar manipulación del monto cobrado a Culqi.
        $ecomChannel     = SalesChannel::ecommerceChannel();
        $ecomWarehouseId = $ecomChannel->warehouse_id;
        $verifiedItems   = [];
        $verifiedTotal   = 0;

        // Batch lookup — evita N+1 (antes: 1 query Item::find + 1 ItemVariant::find por item del carrito).
        $itemIds = collect($culqiItems)->pluck('id')->filter()->unique()->values()->all();
        $variantIds = collect($culqiItems)->pluck('variant_id')->filter()->unique()->values()->all();
        $dbItemsMap    = $itemIds    ? Item::whereIn('id', $itemIds)->get()->keyBy('id')          : collect();
        $dbVariantsMap = $variantIds ? ItemVariant::whereIn('id', $variantIds)->get()->keyBy('id') : collect();

        foreach ($culqiItems as $clientItem) {
            $itemId = $clientItem['id'] ?? null;
            if (!$itemId) continue;

            $dbItem = $dbItemsMap->get($itemId);
            if (!$dbItem || !$dbItem->apply_store) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no disponible en tienda: ' . ($clientItem['description'] ?? $itemId),
                ], 422);
            }

            $qty       = max(1, (int)($clientItem['quantity'] ?? 1));
            $variantId = $clientItem['variant_id'] ?? null;

            if ($variantId) {
                $variant = $dbVariantsMap->get($variantId);
                if (!$variant || !$variant->is_active) {
                    return response()->json(['success' => false, 'message' => 'La variante seleccionada no está disponible.'], 422);
                }
                $realPrice = (float) ($variant->sale_unit_price ?: $dbItem->sale_unit_price);
            } else {
                $iw = $ecomWarehouseId
                    ? ItemWarehouse::where('item_id', $dbItem->id)->where('warehouse_id', $ecomWarehouseId)->first()
                    : ItemWarehouse::where('item_id', $dbItem->id)->orderByDesc('stock_physical')->first();
                $realPrice = (float) $dbItem->sale_unit_price;
            }

            $verifiedTotal += $realPrice * $qty;
            $verifiedItems[] = array_merge($clientItem, [
                'sale_unit_price' => $realPrice,
                'quantity'        => $qty,
                'subtotal'        => $realPrice * $qty,
            ]);
        }

        // Aplicar PromotionEngine (cupón, descuentos automáticos, puntos)
        $pointsRequested = $request->redeem_points && auth('ecommerce')->check()
            ? (float) $request->input('points_amount', (float) optional($user)->accumulated_points)
            : 0;

        try {
            $promo = PromotionEngine::make($verifiedItems, $verifiedTotal)
                ->withCoupon($request->coupon_code ?? null)
                ->withChannel($ecomChannel->id, 'ecommerce')
                ->withPointRedemption($user, $pointsRequested)
                ->calculate();
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $finalTotal     = $promo['final_total'];
        $appliedCoupon  = $promo['applied_coupon'];
        $pointsDiscount = $promo['points_discount'];
        $earnedPoints   = $promo['points_earned'];

        // Culqi recibe el monto en centavos (entero)
        $verifiedAmountCents = (int) round($finalTotal * 100);

        // ── PASO 1: Validar stock Y reservar en una sola transacción atómica ──
        // lockForUpdate dentro de la transacción previene race conditions:
        // dos usuarios concurrentes no pueden pasar la validación al mismo tiempo.
        // La reserva (stock_committed) ocurre ANTES del cobro a Culqi.
        // Si el cobro falla en el PASO 2, se libera en el PASO 3.
        $reservedVariants = [];
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($verifiedItems, $ecomWarehouseId, &$reservedVariants) {
                foreach ($verifiedItems as $clientItem) {
                    $itemId    = $clientItem['id'] ?? null;
                    $variantId = $clientItem['variant_id'] ?? null;
                    $qty       = max(1, (float)($clientItem['quantity'] ?? 1));

                    if ($variantId) {
                        $variant = ItemVariant::find($variantId);
                        if (!$variant || !$variant->is_active) {
                            throw new \Exception('La variante seleccionada no está disponible.');
                        }

                        $vw = ItemVariantWarehouse::where('item_variant_id', $variantId)
                            ->orderByDesc('stock_physical')
                            ->lockForUpdate()
                            ->first();

                        $available = $vw ? max(0, $vw->stock_physical - $vw->stock_committed) : 0;
                        if ($available < $qty) {
                            throw new \Exception('Stock insuficiente para "' . $variant->display_name . '". Disponible: ' . $available);
                        }

                        if ($vw) {
                            $vw->stock_committed += $qty;
                            $vw->save();
                            $reservedVariants[] = ['vw_id' => $vw->id, 'qty' => $qty];
                        }
                    } else {
                        // Re-validate stock for non-variant items (prevents oversell from stale cart)
                        $iw = $ecomWarehouseId
                            ? ItemWarehouse::where('item_id', $itemId)->where('warehouse_id', $ecomWarehouseId)->lockForUpdate()->first()
                            : ItemWarehouse::where('item_id', $itemId)->orderByDesc('stock_physical')->lockForUpdate()->first();

                        if ($iw) {
                            $available = max(0, ($iw->stock_physical ?? $iw->stock) - ($iw->stock_committed ?? 0));
                            if ($available < $qty) {
                                $itemName = Item::find($itemId)->description ?? 'Producto';
                                throw new \Exception('Stock insuficiente para "' . $itemName . '". Disponible: ' . $available);
                            }
                        }
                    }
                }
            });
        } catch (\Exception $stockEx) {
            return response()->json(['success' => false, 'message' => $stockEx->getMessage()], 422);
        }

        // ── PASO 2: Pre-autorización Culqi (capture=false) ────────────────────
        // No capturamos el dinero aún — solo verificamos que la tarjeta tiene fondos.
        // La captura real se hace en background (CapturePaymentJob) para no
        // bloquear el HTTP worker y mejorar el throughput bajo alto tráfico.
        $SECRET_API_KEY = $configuration->token_private_culqui;

        Log::channel('payments')->info('culqi.preauth.attempt', [
            'tenant'       => request()->getHost(),
            'email'        => $request->email,
            'amount_cents' => $verifiedAmountCents,
            'amount_pen'   => $finalTotal,
            'ip'           => $request->ip(),
        ]);

        $culqi = new Culqi(array('api_key' => $SECRET_API_KEY));

        try {
            $charge = $culqi->Charges->create(
                array(
                    "amount"        => $verifiedAmountCents,
                    "currency_code" => "PEN",
                    "email"         => $request->email,
                    "description"   => $request->producto,
                    "source_id"     => $request->token,
                    "installments"  => $request->installments,
                    "capture"       => false,   // pre-autorización sin captura inmediata
                )
            );
        } catch (\Exception $culqiEx) {
            // ── PASO 3: Liberar reserva si la pre-auth falló ──────────────────
            if (!empty($reservedVariants)) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($reservedVariants) {
                    foreach ($reservedVariants as $r) {
                        $vw = ItemVariantWarehouse::lockForUpdate()->find($r['vw_id']);
                        if ($vw) {
                            $vw->stock_committed = max(0, $vw->stock_committed - $r['qty']);
                            $vw->save();
                        }
                    }
                });
            }
            throw $culqiEx;
        }

        Log::channel('payments')->info('culqi.preauth.success', [
            'tenant'    => request()->getHost(),
            'charge_id' => $charge->id ?? null,
            'email'     => $request->email,
        ]);

        $customerData = $customer;
        $customer_name  = $user ? $user->name : ($customerData['apellidos_y_nombres_o_razon_social'] ?? 'Cliente');
        $customer_email = ($request->email ?? null) ?: ($customerData['correo_electronico'] ?? null);
        $shipping_addr  = $isPickup ? 'Recojo en tienda' : ($customerData['direccion'] ?? 'Sin dirección');

        // ── Vincular Person por DNI/RUC (evita duplicados en clientes invitados) ──
        $personId = $user ? $user->id : null;
        if (!$personId) {
            $purchaseRaw = $input['purchase'] ?? $request->purchase ?? [];
            $purchaseData = is_string($purchaseRaw) ? (json_decode($purchaseRaw, true) ?? []) : (is_array($purchaseRaw) ? $purchaseRaw : []);
            $docNumber = $purchaseData['datos_del_cliente_o_receptor']['numero_documento'] ?? null;
            if ($docNumber) {
                $personId = Person::where('number', $docNumber)
                    ->where('type', 'customers')
                    ->value('id');
            }
        }

        // ── Crear Order con estado "pending_capture" ──────────────────────────
        // El cupón, puntos y evento OrderCreated se procesan en CapturePaymentJob
        // después de que la captura sea exitosa (evita inconsistencias si falla).
        $order = Order::create([
            'external_id'       => Str::uuid()->toString(),
            'person_id'         => $personId,
            'customer'          => $customerData,
            'shipping_address'  => $shipping_addr,
            'items'             => $verifiedItems,
            'total'             => $finalTotal,
            // Persistir desglose del descuento para que el comprobante lo refleje
            'subtotal'          => $promo['subtotal'] ?? $verifiedTotal,
            'total_discount'    => $promo['total_discount'] ?? 0,
            'coupon_code'       => $appliedCoupon?->code,
            'discounts'         => $promo['breakdown'] ?? [],
            'points_redeemed'   => $pointsDiscount,
            'points_earned'     => $earnedPoints,
            'reference_payment' => 'culqi',
            'culqi_charge_id'   => $charge->id,
            'payment_status'    => 'pending_capture',
            'purchase'          => $purchaseData,
            'status_order_id'   => 1,               // Pendiente
            'channel_id'        => $ecomChannel->id,
            'warehouse_id'      => $ecomWarehouseId,
            'seller_id'         => null,
        ]);

        // ── PASO 4: Despachar captura en background ───────────────────────────
        $customerPhone = $customerData['telefono'] ?? ($user ? optional($user)->telefono ?? '' : '');

        CapturePaymentJob::dispatch(
            $order->id,
            $charge->id,
            $reservedVariants,
            $appliedCoupon?->code,
            $pointsDiscount,
            $earnedPoints,
            $user?->id,
            $customer_name,
            $customer_email ?? '',
            (string) $customerPhone
        );

        return [
            'success'        => true,
            'culqui'         => $charge,
            'order'          => $order,
            'redirect_route' => url('/ecommerce/order/confirmation/' . $order->external_id),
        ];
      //  return json_encode($charge);
      }
      catch(Exception $e)
      {
        Log::channel('payments')->error('culqi.charge.failed', [
            'tenant'  => request()->getHost(),
            'email'   => $request->email ?? null,
            'error'   => $e->getMessage(),
            'ip'      => request()->ip(),
        ]);

        return [
            'success' => false,
            'message' =>  $e->getMessage()
        ];
      }




    }



}
