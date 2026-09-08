<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Order;
use App\Models\Tenant\OrderStatusLog;
use Illuminate\Support\Facades\Log;

/**
 * El eslabón que faltaba: cobrar un pedido lo hace avanzar.
 *
 * ── El problema ───────────────────────────────────────────────────────────
 *
 * El ENVÍO ya avanzaba solo con el primer cobro (`syncPaymentState`). El
 * PEDIDO no: registrar un cobro no tocaba `status_order_id`, así que el
 * operador tenía que ir al desplegable de la fila y marcar «pago verificado» a
 * mano — sin que nada comprobara que hubiera entrado un sol.
 *
 * Esa es la razón de que en alasitas haya 212 pedidos «verificados» y UN cobro
 * registrado en todo el sistema: la etiqueta no costaba nada y el registro no
 * servía para nada.
 *
 * ── Qué hace, exactamente ─────────────────────────────────────────────────
 *
 * Solo una cosa: cuando un pedido queda saldado —y verificado, si el tenant lo
 * exige— lo mueve de «por confirmar» (1) a «listo para preparar» (2), y lo
 * anota en la bitácora con el motivo.
 *
 * ── Y qué NO hace ─────────────────────────────────────────────────────────
 *
 * **No retrocede.** Si un cobro se rechaza después, el pedido se queda donde
 * está. Suena incompleto y es deliberado: el estado 2 también se pone a mano
 * para los casos legítimos donde el dinero no pasa por aquí —Saga, un cobro en
 * caja que nadie registró— y retroceder automáticamente pisaría una decisión
 * del operador sin saber si fue suya. El chip económico sí vuelve a decir
 * «pendiente», así que la discrepancia queda a la vista en la misma fila.
 *
 * **No salta etapas.** Solo 1 → 2. Un pedido en preparación, despachado o
 * entregado no se toca; y uno cancelado, menos.
 */
class OrderPaymentSync
{
    /**
     * Recalcula el estado del pedido tras un movimiento de dinero.
     *
     * @return bool Si el pedido avanzó.
     */
    public static function sync(Order $order): bool
    {
        // Cancelado, o ya más allá: no hay nada que avanzar.
        if ((int) $order->status_order_id !== 1) {
            return false;
        }

        if (!static::estaSaldado($order)) {
            return false;
        }

        if (PaymentVerification::requerida() && static::tienePendientesDeVerificar($order)) {
            return false;
        }

        $anterior = (int) $order->status_order_id;
        $order->status_order_id = 2;

        // `paid_at` es la fecha comercial del cobro. Nullable en pedidos
        // históricos: no se inventa hacia atrás, pero desde ahora se sella.
        if (!$order->paid_at) {
            $order->paid_at = now();
        }

        $order->save();

        static::anotar($order, $anterior);

        return true;
    }

    /**
     * ¿Está cobrado del todo?
     *
     * El importe sale del pedido, salvo que valga 0 y haya un encargo detrás:
     * entonces vive en el envío. Es la MISMA regla que pinta la fila y que
     * filtra el listado; si divergiera, un pedido avanzaría solo mientras la
     * tabla lo sigue mostrando a deber.
     */
    private static function estaSaldado(Order $order): bool
    {
        $envio = $order->activeShipment;

        $aCobrar = (float) $order->total > 0
            ? (float) $order->total
            : ($envio && $envio->has_amount ? (float) $envio->amount_to_collect : 0.0);

        // Sin importe conocido no se puede afirmar que esté saldado. Un encargo
        // al que nadie le cargó el monto no avanza solo.
        if ($aCobrar <= 0) {
            return false;
        }

        $cobrado = (float) $order->total_paid
                 + ($envio ? (float) $envio->paid_total : 0.0);

        // El céntimo de margen evita que un redondeo deje el pedido parado.
        return $cobrado + 0.009 >= $aCobrar;
    }

    /** ¿Queda algún cobro esperando revisión? */
    private static function tienePendientesDeVerificar(Order $order): bool
    {
        if ($order->payments()->where('verification_status', PaymentVerification::PENDIENTE)->exists()) {
            return true;
        }

        $envio = $order->activeShipment;

        return $envio
            && $envio->payments()->where('verification_status', PaymentVerification::PENDIENTE)->exists();
    }

    /**
     * Deja constancia. Un estado que cambia solo tiene que poder explicarse:
     * sin la anotación, el operador ve el pedido movido y no sabe quién.
     */
    private static function anotar(Order $order, int $anterior): void
    {
        try {
            OrderStatusLog::create([
                'order_id'    => $order->id,
                'from_status' => $anterior,
                'to_status'   => 2,
                'actor_id'    => auth()->id(),
                'payload'     => ['motivo' => 'El pedido quedó saldado al registrarse el cobro.'],
                'created_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // La bitácora no puede tumbar un cobro que YA se guardó.
            Log::warning('[OrderPaymentSync] no se pudo anotar el avance del pedido '
                . $order->id . ': ' . $e->getMessage());
        }
    }
}
