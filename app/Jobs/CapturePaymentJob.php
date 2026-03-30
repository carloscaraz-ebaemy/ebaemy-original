<?php

namespace App\Jobs;

use App\Models\Tenant\ConfigurationEcommerce;
use App\Models\Tenant\Coupon;
use App\Models\Tenant\ItemVariantWarehouse;
use App\Models\Tenant\Order;
use Culqi\Culqi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CapturePaymentJob — captura el cargo pre-autorizado en Culqi.
 *
 * Flujo:
 *   CulqiController::payment() crea un cargo con capture=false (pre-autorización),
 *   crea la Order con payment_status='pending_capture' y despacha este job.
 *
 *   El job:
 *     1. Llama a Culqi Charges->capture($chargeId)
 *     2. Si éxito → payment_status='captured' → incrementa cupón y puntos → evento OrderCreated
 *     3. Si falla → payment_status='capture_failed' → libera stock
 *
 * Reintentos:
 *   Hasta 3 intentos (configurable). Backoff exponencial.
 *   Si falla definitivamente → liberación del stock.
 */
class CapturePaymentJob extends TenantAwareJob
{
    /** @var int ID de la Order en la BD del tenant */
    public int $orderId;

    /** @var string ID del cargo Culqi pre-autorizado (chr_live_xxx) */
    public string $chargeId;

    /** @var array Variantes reservadas para liberar si la captura falla */
    public array $reservedVariants;

    /** @var string|null Código de cupón aplicado (para incrementar used_count) */
    public ?string $couponCode;

    /** @var float Descuento en puntos aplicado */
    public float $pointsDiscount;

    /** @var float Puntos ganados */
    public float $pointsEarned;

    /** @var int|null ID del usuario ecommerce (para actualizar puntos) */
    public ?int $ecommerceUserId;

    // Datos para disparar el evento OrderCreated en el job
    public string $customerName;
    public string $customerEmail;
    public string $customerPhone;

    public int $tries   = 3;
    public int $backoff = 10; // segundos entre reintentos

    public function __construct(
        int     $orderId,
        string  $chargeId,
        array   $reservedVariants,
        ?string $couponCode,
        float   $pointsDiscount,
        float   $pointsEarned,
        ?int    $ecommerceUserId,
        string  $customerName,
        string  $customerEmail,
        string  $customerPhone
    ) {
        parent::__construct();

        $this->orderId           = $orderId;
        $this->chargeId          = $chargeId;
        $this->reservedVariants  = $reservedVariants;
        $this->couponCode        = $couponCode;
        $this->pointsDiscount    = $pointsDiscount;
        $this->pointsEarned      = $pointsEarned;
        $this->ecommerceUserId   = $ecommerceUserId;
        $this->customerName      = $customerName;
        $this->customerEmail     = $customerEmail;
        $this->customerPhone     = $customerPhone;
    }

    public function handle(): void
    {
        $order = Order::find($this->orderId);

        if (!$order) {
            Log::error('[CapturePaymentJob] Order not found.', ['order_id' => $this->orderId]);
            return;
        }

        // Si ya fue capturada (reintento duplicado), salir
        if ($order->payment_status === 'captured') {
            return;
        }

        $configuration = ConfigurationEcommerce::firstCached();
        $culqi = new Culqi(['api_key' => $configuration->token_private_culqui]);

        Log::channel('payments')->info('culqi.capture.attempt', [
            'order_id'  => $this->orderId,
            'charge_id' => $this->chargeId,
        ]);

        try {
            $capture = $culqi->Charges->capture($this->chargeId);

            // ── Captura exitosa ───────────────────────────────────────────────
            DB::transaction(function () use ($order, $capture) {
                // Actualizar estado del pago
                $order->payment_status = 'captured';
                $order->status_order_id = 2; // Pago verificado
                $order->save();

                // Incrementar uso del cupón (atómico con validación de max_uses)
                if ($this->couponCode) {
                    $updated = Coupon::where('code', $this->couponCode)
                        ->whereRaw('used_count < max_uses OR max_uses IS NULL OR max_uses = 0')
                        ->increment('used_count');

                    if (!$updated) {
                        Log::warning('Coupon max_uses exceeded during capture', ['code' => $this->couponCode]);
                    }
                }

                // Actualizar puntos del cliente ecommerce
                if ($this->ecommerceUserId && ($this->pointsDiscount > 0 || $this->pointsEarned > 0)) {
                    $userModel = \App\Models\Tenant\Person::find($this->ecommerceUserId);
                    if ($userModel) {
                        $newBalance = max(0, (float) $userModel->accumulated_points
                            - $this->pointsDiscount
                            + $this->pointsEarned);
                        $userModel->accumulated_points = $newBalance;
                        $userModel->save();
                    }
                }
            });

            Log::channel('payments')->info('culqi.capture.success', [
                'order_id'  => $this->orderId,
                'charge_id' => $this->chargeId,
                'outcome'   => $capture->outcome->user_message ?? 'ok',
            ]);

            // Auto-generate SaleNote from the confirmed order
            try {
                $saleNoteService = app(\App\Services\Tenant\OrderToSaleNoteService::class);
                $saleNoteService->generate($order);
            } catch (\Throwable $snEx) {
                Log::warning('[CapturePaymentJob] SaleNote auto-generation failed', [
                    'order_id' => $this->orderId,
                    'error'    => $snEx->getMessage(),
                ]);
            }

            // Disparar evento para enviar email de confirmación + WhatsApp
            \App\Events\Ecommerce\OrderCreated::dispatch(
                $order,
                $this->customerName,
                $this->customerEmail,
                $this->customerPhone
            );

        } catch (\Throwable $e) {
            Log::channel('payments')->error('culqi.capture.failed', [
                'order_id'  => $this->orderId,
                'charge_id' => $this->chargeId,
                'error'     => $e->getMessage(),
                'attempt'   => $this->attempts(),
            ]);

            // Si es el último intento, marcar como fallido y liberar stock
            if ($this->attempts() >= $this->tries) {
                $this->markAsFailed($order);
            }

            throw $e; // re-lanzar para que el queue maneje el reintento
        }
    }

    /**
     * Llamado por Laravel cuando todos los reintentos se agotaron.
     */
    public function failed(\Throwable $exception): void
    {
        $order = Order::find($this->orderId);
        if ($order) {
            $this->markAsFailed($order);
        }

        Log::channel('payments')->error('culqi.capture.permanently_failed', [
            'order_id'  => $this->orderId,
            'charge_id' => $this->chargeId,
            'error'     => $exception->getMessage(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function markAsFailed(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->payment_status  = 'capture_failed';
            $order->status_order_id = 5; // Cancelado
            $order->save();

            // Liberar stock comprometido
            foreach ($this->reservedVariants as $r) {
                $vw = ItemVariantWarehouse::lockForUpdate()->find($r['vw_id']);
                if ($vw) {
                    $vw->stock_committed = max(0, $vw->stock_committed - $r['qty']);
                    $vw->save();
                }
            }
        });
    }
}
