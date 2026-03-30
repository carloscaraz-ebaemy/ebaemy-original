<?php

namespace App\Services\Tenant\Carrier;

/**
 * DTO con los datos necesarios para crear un envío en el carrier.
 * Todos los campos son los estándar que cualquier carrier peruano requiere.
 */
readonly class ShipmentRequest
{
    public function __construct(
        // ── Referencia interna ─────────────────────────────────────────────
        public int    $saleNoteId,
        public string $orderReference,     // ej: "NV-001-000123"

        // ── Remitente (empresa) ────────────────────────────────────────────
        public string $senderName,
        public string $senderPhone,
        public string $senderAddress,
        public string $senderDistrict,     // ubigeo o nombre
        public string $senderCity,

        // ── Destinatario ───────────────────────────────────────────────────
        public string $recipientName,
        public string $recipientPhone,
        public string $recipientAddress,
        public string $recipientDistrict,
        public string $recipientCity,
        public ?string $recipientEmail = null,
        public ?string $recipientDocNumber = null,

        // ── Paquete ────────────────────────────────────────────────────────
        public int    $packages     = 1,
        public float  $weightKg     = 1.0,
        public ?float $declaredValue = null,  // valor declarado en PEN

        // ── Instrucciones ──────────────────────────────────────────────────
        public ?string $notes       = null,
        public ?string $serviceType = 'estandar',  // estandar, express, same_day

        // ── Costo ──────────────────────────────────────────────────────────
        public float  $shippingCost = 0.0,
        public string $paymentMode  = 'prepaid',  // prepaid | collect | third_party
    ) {}

    public static function fromSaleNote(\App\Models\Tenant\SaleNote $sn, array $companyData = []): self
    {
        return new self(
            saleNoteId:         $sn->id,
            orderReference:     ($sn->series ?? 'NV') . '-' . str_pad($sn->number ?? $sn->id, 6, '0', STR_PAD_LEFT),
            senderName:         $companyData['name']    ?? config('app.name', 'Empresa'),
            senderPhone:        $companyData['phone']   ?? '',
            senderAddress:      $companyData['address'] ?? '',
            senderDistrict:     $companyData['district'] ?? '',
            senderCity:         $companyData['city']    ?? 'Lima',
            recipientName:      $sn->shipping_recipient ?? ($sn->contact_name ?? 'Destinatario'),
            recipientPhone:     $sn->shipping_phone     ?? '',
            recipientAddress:   $sn->shipping_address   ?? '',
            recipientDistrict:  $sn->shipping_city      ?? '',
            recipientCity:      $sn->shipping_city      ?? '',
            packages:           (int)  ($sn->shipping_packages  ?? 1),
            declaredValue:      (float) ($sn->total ?? 0),
            notes:              $sn->shipping_notes     ?? null,
            shippingCost:       (float) ($sn->shipping_cost_agency ?? 0),
            paymentMode:        $sn->shipping_paid_by === 'cliente' ? 'collect' : 'prepaid',
        );
    }
}
