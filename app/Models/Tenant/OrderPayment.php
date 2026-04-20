<?php

namespace App\Models\Tenant;

use App\Models\Tenant\Catalogs\PaymentMethodType;
use Modules\Finance\Models\GlobalPayment;

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
    ];

    protected $casts = [
        'date_of_payment' => 'date',
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
}
