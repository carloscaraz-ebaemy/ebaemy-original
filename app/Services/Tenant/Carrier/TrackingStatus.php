<?php

namespace App\Services\Tenant\Carrier;

/**
 * Estado de tracking normalizado entre carriers.
 *
 * status normalizado:
 *   created      — envío registrado en sistema carrier
 *   picked_up    — recogido por el mensajero
 *   in_transit   — en camino
 *   out_for_delivery — en ruta de entrega final
 *   delivered    — entregado al destinatario
 *   failed       — intento fallido de entrega
 *   returned     — devuelto al remitente
 *   cancelled    — cancelado
 *   unknown      — estado no reconocido
 */
readonly class TrackingStatus
{
    public function __construct(
        public string  $trackingCode,
        public string  $status,               // estado normalizado (ver arriba)
        public string  $statusLabel,          // etiqueta legible en español
        public ?string $lastUpdate    = null, // ISO 8601
        public ?string $location      = null, // ciudad/distrito del último evento
        public ?string $estimatedDelivery = null,
        public array   $events        = [],   // historial de eventos [{date, description, location}]
        public array   $raw           = [],   // respuesta cruda
    ) {}

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isFinalState(): bool
    {
        return in_array($this->status, ['delivered', 'returned', 'cancelled']);
    }
}
