<?php

namespace App\Models\Tenant;

use Modules\Finance\Models\GlobalPayment;
use Modules\Finance\Models\PaymentFile;

/**
 * OrderPayment — Pago asociado a un pedido ecommerce (Order).
 *
 * Estructura paralela a `SaleNotePayment`: al verificar el pago (1→2) el admin
 * registra uno o varios pagos. Luego al generar la Nota de Venta desde este
 * pedido, los `OrderPayment` se copian a `SaleNotePayment`.
 *
 * Un OrderPayment puede tener un GlobalPayment asociado (relación polimórfica)
 * cuando el pago va a una caja o cuenta bancaria específica.
 */
class OrderPayment extends ModelTenant
{
    protected $with = ['payment_method_type'];

    protected $fillable = [
        'order_id',
        'date_of_payment',
        'payment_method_type_id',
        'has_card',
        'card_brand_id',
        'reference',
        'change',
        'payment',
        'payment_destination_id',
        // Quien registro el cobro. `shipping_payments` ya lo guardaba y este no,
        // asi que un cobro mal cargado no tenia autor.
        'created_by',
        // Verificacion del cobro. Ver `PaymentVerification`: registrar un
        // cobro y comprobar que es valido son dos hechos distintos.
        'verification_status',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected $casts = [
        'date_of_payment' => 'date',
        'verified_at'     => 'datetime',

        'has_card'        => 'boolean',
        'change'          => 'decimal:2',
        'payment'         => 'decimal:2',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payment_method_type()
    {
        return $this->belongsTo(PaymentMethodType::class);
    }

    public function card_brand()
    {
        return $this->belongsTo(CardBrand::class);
    }

    public function global_payment()
    {
        return $this->morphOne(GlobalPayment::class, 'payment');
    }

    /**
     * Alias que esperan FinanceTrait/FilePaymentTrait para llegar al registro
     * origen sin saber de que tipo de pago se trata.
     */
    public function associated_record_payment()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /** Comprobante adjunto del pago. Faltaba: el voucher solo se podia ver en nota de venta. */
    public function payment_file()
    {
        return $this->morphOne(PaymentFile::class, 'payment');
    }

    public function getPaymentFileUrl()
    {
        return optional($this->payment_file)->getFileUrl('orders');
    }
}
