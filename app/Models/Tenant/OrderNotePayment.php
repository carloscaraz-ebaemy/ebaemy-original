<?php

namespace App\Models\Tenant;

use Modules\Finance\Models\GlobalPayment;
use Modules\Finance\Models\PaymentFile;
use Modules\Order\Models\OrderNote;

/**
 * OrderNotePayment — Pago de un Pedido del ERP (order_notes).
 *
 * Mismos campos que SaleNotePayment y OrderPayment, para que las tres pantallas
 * de pagos se comporten igual. Antes order_notes solo tenía un
 * `payment_method_type_id` en la cabecera: no admitía pagos parciales ni varios
 * pagos, y no había forma de saber el saldo.
 *
 * El archivo del pago va por `payment_file` (morph a payment_files) y el asiento
 * contable por `global_payment`, igual que en nota de venta.
 */
class OrderNotePayment extends ModelTenant
{
    protected $with = ['payment_method_type'];

    protected $fillable = [
        'order_note_id',
        'date_of_payment',
        'payment_method_type_id',
        'has_card',
        'card_brand_id',
        'reference',
        'change',
        'payment',
        'payment_destination_id',
    ];

    protected $casts = [
        'date_of_payment' => 'date',
        'has_card'        => 'boolean',
        'change'          => 'decimal:2',
        'payment'         => 'decimal:2',
    ];

    public function order_note()
    {
        return $this->belongsTo(OrderNote::class, 'order_note_id');
    }

    /**
     * Alias que espera FinanceTrait/FilePaymentTrait para llegar al registro
     * origen del pago sin saber de qué tipo es.
     */
    public function associated_record_payment()
    {
        return $this->belongsTo(OrderNote::class, 'order_note_id');
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

    public function payment_file()
    {
        return $this->morphOne(PaymentFile::class, 'payment');
    }

    public function getPaymentFileUrl()
    {
        return optional($this->payment_file)->getFileUrl('order_notes');
    }
}
