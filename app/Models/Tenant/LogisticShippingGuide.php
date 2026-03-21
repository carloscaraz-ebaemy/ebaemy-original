<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LogisticShippingGuide extends ModelTenant
{
    protected $table = 'logistic_shipping_guides';

    protected $fillable = [
        'logistic_order_id',
        'sale_note_id',
        'dispatch_id',
        'carrier_name',
        'carrier_ruc',
        'carrier_plate',
        'driver_name',
        'driver_license',
        'origin_address',
        'destination_address',
        'destination_ubigeo',
        'series',
        'number',
        'pdf_path',
        'tracking_code',
        'dispatch_date',
        'status',
        'issued_by',
    ];

    protected $casts = [
        'dispatch_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(LogisticOrder::class, 'logistic_order_id');
    }

    /**
     * URL pública del PDF de la guía de remisión.
     * Los PDFs se almacenan en storage/tenant/{uuid}/shipping_guides/
     */
    public function getPdfUrlAttribute(): ?string
    {
        if (!$this->pdf_path) {
            return null;
        }
        return Storage::url($this->pdf_path);
    }

    /**
     * Nombre del documento formateado: Serie-Número.
     */
    public function getFullNumberAttribute(): string
    {
        return $this->series && $this->number
            ? "{$this->series}-{$this->number}"
            : 'Sin número';
    }
}
