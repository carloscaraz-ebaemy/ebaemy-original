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
        // Método desde el catálogo y destino (caja / cuenta), como en nota de
        // venta. `method` se conserva para los pagos ya cargados.
        'payment_method_type_id',
        'payment_destination_id',
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

    /**
     * Cómo se cobró. Primero el método del catálogo (el nuevo campo), después
     * el texto libre que se usaba antes, para que los pagos ya cargados sigan
     * mostrándose bien.
     */
    public function getMethodLabelAttribute(): string
    {
        $catalogo = optional($this->payment_method_type)->description;
        if ($catalogo) return $catalogo;

        return self::METHODS[$this->method] ?? ($this->method ?: '—');
    }

    public function payment_method_type()
    {
        return $this->belongsTo(PaymentMethodType::class, 'payment_method_type_id');
    }

    /**
     * Asiento en Finanzas. Sin esto el cobro del envío quedaba fuera de caja:
     * la plata entraba pero no figuraba en ningún arqueo.
     */
    public function global_payment()
    {
        return $this->morphOne(\Modules\Finance\Models\GlobalPayment::class, 'payment');
    }

    /** Voucher del pago. Va en payment_files, igual que en nota de venta. */
    public function payment_file()
    {
        return $this->morphOne(\Modules\Finance\Models\PaymentFile::class, 'payment');
    }

    /** Alias que esperan los traits de Finanzas para llegar al registro origen. */
    public function associated_record_payment()
    {
        return $this->belongsTo(ShippingRequest::class, 'shipment_id');
    }
}
