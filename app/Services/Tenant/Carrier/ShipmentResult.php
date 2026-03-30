<?php

namespace App\Services\Tenant\Carrier;

/**
 * Resultado de createShipment() — normalizado entre carriers.
 */
readonly class ShipmentResult
{
    public function __construct(
        public string  $trackingCode,         // código de tracking del carrier
        public ?string $labelUrl    = null,   // URL del PDF de etiqueta (si el carrier lo provee)
        public ?string $externalId  = null,   // ID interno del carrier para este envío
        public ?float  $quotedCost  = null,   // costo cotizado por el carrier (PEN)
        public ?string $estimatedDelivery = null, // fecha estimada (ISO 8601)
        public array   $raw         = [],     // respuesta cruda del carrier para auditoría
    ) {}
}
