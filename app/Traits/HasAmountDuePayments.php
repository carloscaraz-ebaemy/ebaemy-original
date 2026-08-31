<?php

namespace App\Traits;

/**
 * HasAmountDuePayments — cuánto hay que cobrar, cuánto se cobró y cuánto falta.
 *
 * Lo usan Order (pedido del ecommerce) y OrderNote (pedido del ERP). Los dos
 * calculan `total` sumando sus productos, pero hay pedidos donde los productos
 * no tienen precio cargado y ese total no sirve para cobrar. Para esos casos
 * existe `amount_due`, que cuando está seteado manda sobre `total`.
 *
 * `total` NO se toca nunca desde acá: OrderToSaleNoteService lo copia tal cual
 * a la Nota de Venta y en order_notes alimenta el PDF. Pisarlo corrompería el
 * comprobante.
 *
 * El modelo que use este trait necesita:
 *   - columnas `total` y `amount_due`
 *   - relación `payments()` con un modelo que tenga columna `payment`
 */
trait HasAmountDuePayments
{
    /**
     * Monto a cobrar: el manual si está cargado, si no el de los productos.
     *
     * Se compara contra null y no con empty(): un `amount_due` de 0 es una
     * decisión válida del usuario (pedido de cortesía) y empty() lo trataría
     * como "sin cargar", volviendo al total de los productos.
     */
    public function getAmountToCollectAttribute(): float
    {
        return $this->amount_due !== null
            ? (float) $this->amount_due
            : (float) ($this->total ?? 0);
    }

    /** ¿El monto a cobrar lo escribió una persona en vez de salir de los productos? */
    public function getHasManualAmountAttribute(): bool
    {
        return $this->amount_due !== null;
    }

    /** Suma de los pagos registrados. */
    public function getTotalPaidAttribute(): float
    {
        return round((float) $this->payments()->sum('payment'), 2);
    }

    /**
     * Saldo pendiente. Nunca negativo: si alguien cargó de más, el saldo es
     * cero y la diferencia se ve en el vuelto, no en un número rojo raro.
     */
    public function getTotalDifferenceAttribute(): float
    {
        return round(max(0, $this->amount_to_collect - $this->total_paid), 2);
    }

    /** ¿Está saldado? Con monto a cobrar en cero se considera saldado. */
    public function getIsFullyPaidAttribute(): bool
    {
        return $this->total_difference <= 0;
    }

    /**
     * Bloque que consumen las pantallas de pagos. Mismas claves que devuelve
     * SaleNotePaymentController::document, para que el componente Vue sea uno
     * solo para las tres pantallas.
     */
    public function getPaymentSummary(): array
    {
        return [
            'total'             => round((float) ($this->total ?? 0), 2),
            'amount_due'        => $this->amount_due !== null ? round((float) $this->amount_due, 2) : null,
            'amount_to_collect' => round($this->amount_to_collect, 2),
            'has_manual_amount' => $this->has_manual_amount,
            'total_paid'        => $this->total_paid,
            'total_difference'  => $this->total_difference,
            'paid'              => $this->is_fully_paid,
        ];
    }
}
