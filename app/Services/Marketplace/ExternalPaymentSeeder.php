<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\MarketplaceOrder;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;
use App\Services\Tenant\PaymentVerification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Deja constancia del cobro que hizo el canal.
 *
 * ── Por qué existe ────────────────────────────────────────────────────────
 *
 * Un pedido de Saga entraba en estado 2 («pago verificado») con `paid_at`
 * sellado y CERO cobros registrados. El panel calculaba entonces un saldo igual
 * al total, así que mostraba a deber una venta ya cobrada — y, peor, el único
 * guardarrail contra el sobrepago es precisamente ese saldo: un cobro manual por
 * el importe completo cabía y se aceptaba. Auditado el 2026-09-16 ejecutando el
 * flujo real: «Pago registrado con éxito» sobre un pedido que Falabella ya había
 * cobrado, con su asiento en caja.
 *
 * Al sembrar el cobro, el saldo queda en cero y ese mismo guardarrail pasa a
 * proteger a los pedidos del canal. No hace falta una regla nueva que diga «no
 * dejes cobrar pedidos de Saga»: sobra con que el cobro exista.
 *
 * ── Qué NO hace: caja ─────────────────────────────────────────────────────
 *
 * **No crea asiento en `global_payments`.** Decisión de negocio (2026-09-16): ese
 * dinero no entra por la caja del tenant, lo cobra y lo liquida Falabella. El
 * cobro existe para que el pedido cuadre y no se pueda cobrar dos veces, no para
 * mover caja. La otra mitad de esa regla vive en los informes: la caja debe sumar
 * solo los cobros con `source = MANUAL`.
 *
 * ── Idempotente ──────────────────────────────────────────────────────────
 *
 * La clave es (`order_id`, `external_reference`), con único en la base. Reimportar
 * el mismo pedido, o pasar el comando de relleno dos veces, no siembra dos cobros.
 */
class ExternalPaymentSeeder
{
    /**
     * Siembra el cobro del canal si corresponde.
     *
     * @param  bool  $dryRun  Con true no escribe: devuelve el cobro que CREARÍA,
     *                        pasando por exactamente las mismas comprobaciones.
     *                        Lo usa el comando de siembra retroactiva, donde
     *                        mirar antes de escribir dinero no es opcional.
     * @return OrderPayment|null  El cobro (nuevo o el que ya estaba), o null si
     *                            no se sembró. `$motivo` explica por qué no.
     */
    public function seed(Order $order, MarketplaceOrder $mpOrder, ?string &$motivo = null, bool $dryRun = false): ?OrderPayment
    {
        $motivo = null;

        // Tenant sin la migración todavía: no se rompe la importación por esto.
        // Un pedido sin cobro sembrado es el comportamiento anterior, no una venta perdida.
        if (!Schema::connection('tenant')->hasColumn('order_payments', 'source')) {
            $motivo = 'El tenant aún no tiene la columna order_payments.source.';

            return null;
        }

        $importe = round((float) $order->total, 2);

        if ($importe <= 0) {
            $motivo = 'El pedido no tiene importe: no hay cobro que registrar.';

            return null;
        }

        $referencia = trim((string) $mpOrder->external_order_id);

        if ($referencia === '') {
            $motivo = 'El pedido del canal no trae identificador externo: sin él no se puede sembrar de forma idempotente.';

            return null;
        }

        // Ya sembrado (reimportación, o el comando de relleno pasando otra vez).
        $existente = OrderPayment::where('order_id', $order->id)
            ->where('external_reference', $referencia)
            ->first();

        if ($existente) {
            return $existente;
        }

        // Alguien ya cobró esto a mano —hoy se puede, y es el conflicto que hay
        // que mirar de frente—. Sembrar encima duplicaría el importe.
        if ($this->tieneCobrosManuales($order)) {
            $motivo = 'El pedido ya tiene cobros registrados a mano. Revísalos antes de sembrar el del canal: sembrar encima duplicaría el importe.';

            return null;
        }

        $pago = new OrderPayment();
        $pago->order_id = $order->id;
        $pago->date_of_payment = ($mpOrder->ordered_at ?: now())->toDateString();
        $pago->payment_method_type_id = $this->metodo($order);
        $pago->has_card = false;
        $pago->payment = $importe;
        // La referencia visible del cobro es el número de pedido en el canal:
        // es el dato con el que el operador lo busca en el portal del vendedor.
        $pago->reference = $referencia;
        // Sin destino: no entra a ninguna caja ni cuenta del tenant.
        $pago->payment_destination_id = null;
        // Nace verificado y sin autor: no hay nada que comprobar y no lo registró
        // ninguna persona. Marcarlo «pendiente» dejaría el pedido sin avanzar
        // esperando una revisión que nadie puede hacer.
        $pago->verification_status = PaymentVerification::VERIFICADO;
        $pago->created_by = null;

        $pago->source = OrderPayment::SOURCE_SAGA;
        $pago->external_reference = $referencia;

        if ($dryRun) {
            return $pago; // sin guardar: el llamante solo quiere saber qué haría
        }

        $pago->save();

        Log::channel('payments')->info('Cobro del canal sembrado', [
            'order_id'   => $order->id,
            'referencia' => $referencia,
            'importe'    => $importe,
        ]);

        return $pago;
    }

    /** ¿Hay cobros que registró una persona? Los de otros canales no cuentan. */
    protected function tieneCobrosManuales(Order $order): bool
    {
        return OrderPayment::where('order_id', $order->id)
            ->where(function ($q) {
                $q->where('source', OrderPayment::SOURCE_MANUAL)
                  ->orWhereNull('source')
                  ->orWhere('source', '');
            })
            ->exists();
    }

    /**
     * Con qué método se estampa el cobro.
     *
     * Saga no informa el método de pago —o al menos no se guarda hoy; qué trae
     * de verdad `GetOrders` está sin verificar—, así que aquí NO se inventa uno.
     * Se usa el mismo valor que `OrderToSaleNoteService` ya estampa en la nota de
     * venta de este pedido, para que los dos libros no digan cosas distintas
     * sobre el mismo cobro. Quien mira la fila sabe de dónde viene el dinero por
     * `source`, que es el dato que sí es cierto.
     */
    protected function metodo(Order $order): string
    {
        $ref = mb_strtolower((string) $order->reference_payment);

        if (str_contains($ref, 'culqi') || str_contains($ref, 'card') || str_contains($ref, 'tarjeta')) {
            return '02';
        }

        if (str_contains($ref, 'transfer')) {
            return '03';
        }

        return '01';
    }
}
