<?php

namespace App\Services\System;

use App\Models\System\MarketplaceOrder;
use App\Models\System\MarketplaceOrderItem;
use Illuminate\Support\Facades\Log;
use MercadoPago\SDK as MPSDK;
use MercadoPago\Preference;
use MercadoPago\Item as MPItem;
use MercadoPago\Payer as MPPayer;
use MercadoPago\Payment as MPPayment;

/**
 * Integración con MercadoPago Checkout Pro para el marketplace ebaemy.
 *
 * Flujo:
 *   1) Customer completa datos en /marketplace/checkout
 *   2) MarketplaceCheckoutService crea MarketplaceOrder + items + sub-orders
 *      con payment_status='unpaid'
 *   3) Esta clase crea una Preference en MP, guarda mp_preference_id +
 *      mp_init_point en la orden, y devuelve la URL de checkout
 *   4) Customer es redirigido a checkout MP, paga, MP redirige al
 *      success/failure callback
 *   5) MP envía webhook al endpoint /marketplace/payment/webhook
 *      con el payment_id; aquí verificamos y marcamos como paid
 *   6) Cuando paid → MarketplaceMultiOrderDispatcher lanza los sub-pedidos
 *      a los tenants (extraído de MarketplaceCheckoutService)
 *
 * Idempotente: validar mp_payment_id antes de re-procesar webhooks duplicados.
 */
class MercadoPagoService
{
    /**
     * Inicializa el SDK con el access token configurado.
     * Lanza excepción si no hay token (deja explícito que MP no está activo).
     */
    public function __construct()
    {
        $token = config('services.mercadopago.access_token');
        if (empty($token)) {
            throw new \RuntimeException(
                'MercadoPago no configurado. Define MP_ACCESS_TOKEN en .env'
            );
        }
        MPSDK::setAccessToken($token);
    }

    /**
     * Crea una preferencia de pago en MP para un MarketplaceOrder.
     * Persiste mp_preference_id + mp_init_point + payment_provider en la orden.
     *
     * @return array{success: bool, init_point?: string, preference_id?: string, error?: string}
     */
    public function createPreferenceForOrder(MarketplaceOrder $order): array
    {
        $items = MarketplaceOrderItem::where('marketplace_order_id', $order->id)->get();
        if ($items->isEmpty()) {
            return ['success' => false, 'error' => 'La orden no tiene items.'];
        }

        try {
            $preference = new Preference();

            // Items en formato MP — title máx 256, description opcional.
            $mpItems = [];
            foreach ($items as $line) {
                $item = new MPItem();
                $item->id          = (string) $line->id;
                $item->title        = mb_substr((string) $line->title, 0, 256);
                $item->quantity     = (int) $line->quantity;
                $item->unit_price   = (float) $line->unit_price;
                $item->currency_id  = 'PEN';
                if ($line->image_url) {
                    $item->picture_url = $line->image_url;
                }
                $mpItems[] = $item;
            }
            $preference->items = $mpItems;

            // Datos del comprador
            $payer = new MPPayer();
            $payer->name  = $order->customer_name;
            $payer->email = $order->customer_email ?: 'sin-email@ebaemy.com';
            if ($order->customer_phone) {
                $payer->phone = (object) [
                    'area_code' => '51',
                    'number'    => preg_replace('/\D+/', '', $order->customer_phone),
                ];
            }
            if ($order->customer_doc_number) {
                $payer->identification = (object) [
                    'type'   => $order->customer_doc_type ?: 'DNI',
                    'number' => $order->customer_doc_number,
                ];
            }
            $preference->payer = $payer;

            // External reference para mapear webhook → MarketplaceOrder
            $preference->external_reference = $order->order_number;

            // URLs back: success / failure / pending
            $base = rtrim(config('app.url'), '/');
            $preference->back_urls = [
                'success' => $base . '/marketplace/payment/return?status=success',
                'failure' => $base . '/marketplace/payment/return?status=failure',
                'pending' => $base . '/marketplace/payment/return?status=pending',
            ];
            $preference->auto_return = 'approved';

            // Notificación webhook
            $preference->notification_url = $base . '/marketplace/payment/webhook';

            // Statement descriptor que aparece en el voucher del banco
            $preference->statement_descriptor = 'EBAEMY';

            // Saved + atomic — o falla, o se persiste con preference_id
            $preference->save();

            if (empty($preference->id)) {
                $error = $preference->error->message ?? 'Sin respuesta de MP';
                Log::error('[MercadoPago] No se pudo crear preferencia', [
                    'order'  => $order->order_number,
                    'errors' => $preference->error,
                ]);
                return ['success' => false, 'error' => $error];
            }

            // Persistir en la orden
            $order->payment_provider     = 'mercadopago';
            $order->mp_preference_id     = $preference->id;
            $order->mp_init_point        = config('services.mercadopago.sandbox')
                                            ? $preference->sandbox_init_point
                                            : $preference->init_point;
            $order->payment_attempted_at = now();
            $order->save();

            Log::info('[MercadoPago] Preferencia creada', [
                'order'         => $order->order_number,
                'preference_id' => $preference->id,
            ]);

            return [
                'success'       => true,
                'init_point'    => $order->mp_init_point,
                'preference_id' => $preference->id,
            ];
        } catch (\Throwable $e) {
            Log::error('[MercadoPago] Excepción creando preferencia', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Procesa una notificación webhook de MP.
     * Espera $paymentId desde body.data.id (topic=payment).
     *
     * Valida con MP el estado real del pago (NO confiar solo en el body).
     * Si está approved, marca la orden como paid e invoca el dispatcher
     * para enviar sub-orders a los tenants.
     *
     * @return array{success: bool, order?: MarketplaceOrder, status?: string, message?: string}
     */
    public function handleWebhook(string $paymentId): array
    {
        try {
            $payment = MPPayment::find_by_id($paymentId);
            if (!$payment || empty($payment->id)) {
                return ['success' => false, 'message' => 'Payment no encontrado en MP'];
            }

            $externalRef = $payment->external_reference ?? null;
            if (!$externalRef) {
                return ['success' => false, 'message' => 'Payment sin external_reference'];
            }

            $order = MarketplaceOrder::where('order_number', $externalRef)->first();
            if (!$order) {
                return ['success' => false, 'message' => "Orden no encontrada: {$externalRef}"];
            }

            // Idempotente: si ya está paid con este payment_id, no reprocesar
            if ($order->payment_status === 'paid' && $order->mp_payment_id === (string) $payment->id) {
                return ['success' => true, 'order' => $order, 'status' => 'already_paid'];
            }

            $order->mp_payment_id     = (string) $payment->id;
            $order->mp_payment_status = $payment->status; // approved | pending | rejected | in_process

            if ($payment->status === 'approved') {
                $order->payment_status = 'paid';
                $order->payment_paid_at = now();
                $order->save();

                // Dispatch a tenants ahora que el pago confirmó
                try {
                    app(\App\Services\System\MarketplaceMultiOrderDispatcher::class)
                        ->dispatchOrder($order);
                } catch (\Throwable $e) {
                    Log::error('[MercadoPago] Dispatch a tenants falló post-payment', [
                        'order' => $order->order_number,
                        'error' => $e->getMessage(),
                    ]);
                }

                return ['success' => true, 'order' => $order, 'status' => 'paid'];
            }

            // Estados no-final: pending, in_process, in_mediation, etc.
            // Solo persistimos el estado MP, no cambiamos payment_status.
            $order->save();
            return ['success' => true, 'order' => $order, 'status' => $payment->status];

        } catch (\Throwable $e) {
            Log::error('[MercadoPago] Excepción en webhook', [
                'payment_id' => $paymentId,
                'error'      => $e->getMessage(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
