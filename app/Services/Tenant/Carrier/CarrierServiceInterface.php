<?php

namespace App\Services\Tenant\Carrier;

/**
 * Contrato común para todos los carriers (couriers) integrados via API.
 *
 * Cada carrier concreto (Chazki, 99Minutos, Olva, etc.) implementa esta interfaz.
 * El factory `CarrierServiceFactory` crea la instancia correcta según `api_driver`.
 *
 * Carrier que no tenga API devuelve `ManualCarrierService`, que no hace nada.
 */
interface CarrierServiceInterface
{
    /**
     * Identificador del driver (debe coincidir con courier_companies.api_driver).
     */
    public function getDriver(): string;

    /**
     * Crear un envío en el sistema del carrier.
     *
     * @param  ShipmentRequest  $request  Datos del pedido a enviar
     * @return ShipmentResult            Resultado con tracking_number y datos del envío
     * @throws CarrierApiException       Si la API falla o devuelve error
     */
    public function createShipment(ShipmentRequest $request): ShipmentResult;

    /**
     * Obtener el estado de tracking de un envío ya creado.
     *
     * @param  string  $trackingCode  Código de tracking devuelto por createShipment()
     * @return TrackingStatus         Estado actual del envío
     * @throws CarrierApiException
     */
    public function getTracking(string $trackingCode): TrackingStatus;

    /**
     * Cancelar un envío (si el carrier lo permite).
     *
     * @param  string  $trackingCode
     * @return bool    true si cancelado, false si no soportado
     * @throws CarrierApiException
     */
    public function cancelShipment(string $trackingCode): bool;

    /**
     * ¿Tiene integración API activa? (false = ManualCarrierService)
     */
    public function hasApiIntegration(): bool;
}
