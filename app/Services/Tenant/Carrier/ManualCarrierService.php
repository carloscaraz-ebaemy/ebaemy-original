<?php

namespace App\Services\Tenant\Carrier;

/**
 * Servicio "sin integración" — para couriers sin API.
 * El operador ingresa el tracking manualmente en el formulario de despacho.
 * No lanza excepciones ni hace llamadas HTTP.
 */
class ManualCarrierService implements CarrierServiceInterface
{
    public function getDriver(): string { return 'manual'; }

    public function hasApiIntegration(): bool { return false; }

    public function createShipment(ShipmentRequest $request): ShipmentResult
    {
        // No hay API — devolver resultado vacío para que el operador llene el tracking
        return new ShipmentResult(
            trackingCode: '',
            raw: ['driver' => 'manual', 'message' => 'Tracking manual requerido'],
        );
    }

    public function getTracking(string $trackingCode): TrackingStatus
    {
        return new TrackingStatus(
            trackingCode: $trackingCode,
            status: 'unknown',
            statusLabel: 'Tracking manual — sin integración API',
        );
    }

    public function cancelShipment(string $trackingCode): bool
    {
        return false; // No soportado en modo manual
    }
}
