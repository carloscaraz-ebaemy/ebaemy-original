<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

/**
 * Un pago concreto de un envío (multipago).
 *
 * Un pedido se cobra en varias operaciones —el cliente paga, agrega otro
 * producto y vuelve a pagar—, así que cada pago lleva su MONTO y su CÓDIGO de
 * operación. El código es único en toda la tienda: el mismo voucher no puede
 * usarse en dos envíos ni dos veces en el mismo.
 */
class ShippingPayment extends Model
{
    protected $connection = 'tenant';

    protected $table = 'shipping_payments';

    protected $fillable = [
        'shipment_id',
        'amount',
        'payment_code',
        'payment_code_normalized',
        'method',
        'note',
        'paid_at',
        'created_by',
        'created_by_name',
    ];

    protected $casts = [
        'shipment_id' => 'integer',
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
        'created_by'  => 'integer',
    ];

    /** Medios de pago frecuentes (el operador puede dejarlo vacío). */
    public const METHODS = [
        'yape'          => 'Yape',
        'plin'          => 'Plin',
        'transferencia' => 'Transferencia',
        'efectivo'      => 'Efectivo',
        'tarjeta'       => 'Tarjeta',
        'otro'          => 'Otro',
    ];

    public function shipment()
    {
        return $this->belongsTo(ShippingRequest::class, 'shipment_id');
    }

    /**
     * Normaliza el código antes de guardar: es el valor con el que se comparan
     * los duplicados, así que nunca debe quedar desalineado del código escrito.
     */
    protected static function booted(): void
    {
        static::saving(function (self $payment) {
            $payment->payment_code_normalized =
                ShippingRequest::normalizePaymentCode($payment->payment_code) ?: null;
        });
    }

    public function getMethodLabelAttribute(): string
    {
        return self::METHODS[$this->method] ?? ($this->method ?: '—');
    }
}
