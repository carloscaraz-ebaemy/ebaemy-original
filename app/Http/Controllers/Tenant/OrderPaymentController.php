<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\ManagesRecordPayments;
use App\Models\Tenant\Order;
use App\Models\Tenant\OrderPayment;

/**
 * Pagos de un Pedido del ecommerce.
 *
 * Los pagos ya se guardaban desde OrderController::saveOrderPayments al
 * verificar el pedido, pero de a bloque: borraba todo y reescribía. Este
 * controlador agrega lo que faltaba —alta y baja individual, archivo adjunto,
 * saldo y monto a cobrar manual— compartiendo la lógica con los pedidos del
 * ERP y con el mismo comportamiento que nota de venta.
 *
 * OrderController::saveOrderPayments sigue existiendo para el flujo de
 * verificación; los dos escriben sobre la misma tabla.
 */
class OrderPaymentController extends Controller
{
    use ManagesRecordPayments;

    protected function paymentOwner(int $id)
    {
        return Order::findOrFail($id);
    }

    /**
     * Un pedido anulado no admite movimientos de dinero.
     *
     * Delega en el modelo para no tener aqui una segunda definicion de
     * «bloqueado»: es la misma que consultan el listado y el resto de los
     * endpoints de escritura.
     */
    protected function paymentLockReason($owner): ?string
    {
        return $owner instanceof Order ? $owner->motivoBloqueoModificacion() : null;
    }

    /**
     * El cobro que trajo el canal no se edita ni se borra desde aqui.
     *
     * No describe una decision nuestra sino un hecho ocurrido fuera: Falabella
     * le cobro al comprador y liquida por su cuenta. Corregirlo o borrarlo desde
     * EBAEMY dejaria el pedido diciendo que se debe algo que ya esta cobrado —y,
     * borrado el cobro, el hueco vuelve a admitir un cobro manual encima, que es
     * exactamente el problema que esto cierra.
     */
    protected function paymentRecordLockReason($record): ?string
    {
        if (!$record instanceof OrderPayment || !$record->esExterno()) {
            return null;
        }

        return 'Este cobro lo hizo ' . ($record->origenLabel() ?: 'el canal de venta')
             . ' y aqui solo se consulta: no se puede editar ni eliminar. '
             . 'Si el importe no coincide, corrigelo en el portal del canal.';
    }

    protected function paymentModelClass(): string
    {
        return OrderPayment::class;
    }

    protected function paymentForeignKey(): string
    {
        return 'order_id';
    }

    protected function paymentFileFolder(): string
    {
        return 'orders';
    }
}
