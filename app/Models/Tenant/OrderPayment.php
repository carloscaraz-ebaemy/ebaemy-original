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
 *
 * ── Origen del cobro ──────────────────────────────────────────────────────
 *
 * `source` dice quién lo creó, y de eso depende qué se puede hacer con él. Un
 * cobro MANUAL lo teclea un usuario y se edita y se borra; uno que trajo una
 * integración describe un hecho que ocurrió FUERA de EBAEMY —Falabella le cobró
 * al comprador y liquida por su cuenta— y aquí solo se muestra.
 *
 * `source` NO está en $fillable a propósito: el panel de pagos rellena el modelo
 * con `fill($request->all())`, y si fuera asignable en masa una petición a mano
 * podría crear un cobro haciéndolo pasar por uno del canal —justo el que después
 * nadie puede borrar—. Lo escribe explícitamente quien tiene derecho a hacerlo.
 */
class OrderPayment extends ModelTenant
{
    /** Lo registró una persona en el panel. Editable y borrable. */
    public const SOURCE_MANUAL = 'MANUAL';

    /** Lo cobró Saga Falabella. Solo lectura en EBAEMY. */
    public const SOURCE_SAGA = 'SAGA';

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
        // `source` y `external_reference` quedan FUERA a proposito (ver arriba):
        // los escribe el sembrador del cobro externo, nunca el formulario.
    ];

    protected $casts = [
        'date_of_payment' => 'date',
        'verified_at'     => 'datetime',

        'has_card'        => 'boolean',
        'change'          => 'decimal:2',
        'payment'         => 'decimal:2',
    ];

    /**
     * ¿Lo trajo una integración en vez de una persona?
     *
     * Se compara con MANUAL y no con la lista de orígenes externos: el día que
     * entre otro canal, su cobro nace protegido sin tocar esta función. Un valor
     * vacío es una fila anterior a la migración, y esas son todas manuales.
     */
    public function esExterno(): bool
    {
        $origen = (string) ($this->source ?? self::SOURCE_MANUAL);

        return $origen !== '' && $origen !== self::SOURCE_MANUAL;
    }

    /** Nombre del canal que cobró, para la pantalla. */
    public function origenLabel(): ?string
    {
        if (!$this->esExterno()) {
            return null;
        }

        return match ((string) $this->source) {
            self::SOURCE_SAGA => 'Saga Falabella',
            default           => (string) $this->source,
        };
    }

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
