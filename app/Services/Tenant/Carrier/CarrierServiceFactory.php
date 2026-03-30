<?php

namespace App\Services\Tenant\Carrier;

use App\Models\Tenant\CourierCompany;

/**
 * Factory que instancia el CarrierService correcto según el api_driver
 * configurado en la tabla courier_companies.
 *
 * Para añadir un nuevo carrier:
 *   1. Crear una clase que implemente CarrierServiceInterface
 *   2. Añadir el driver aquí en el match
 *   3. Configurar api_driver, api_key, api_endpoint en el registro de CourierCompany
 *
 * Uso:
 *   $carrier = CarrierServiceFactory::make($courierCompany);
 *   $result  = $carrier->createShipment($request);
 */
class CarrierServiceFactory
{
    /**
     * Crea el servicio de carrier para la empresa de courier dada.
     * Devuelve ManualCarrierService si no hay API configurada.
     */
    public static function make(CourierCompany $courier): CarrierServiceInterface
    {
        $driver   = $courier->api_driver   ?? 'manual';
        $apiKey   = $courier->api_key      ?? '';
        $endpoint = $courier->api_endpoint ?? '';
        $sandbox  = (bool) ($courier->api_sandbox ?? false);
        $meta     = is_array($courier->api_meta) ? $courier->api_meta
            : (json_decode((string) $courier->api_meta, true) ?? []);

        return match ($driver) {
            'chazki'    => new ChizkiCarrierService($apiKey, $endpoint, $sandbox, $meta),
            '99minutos' => new NueveMintosCarrierService($apiKey, $endpoint, $sandbox, $meta),
            default     => new ManualCarrierService(),
        };
    }

    /**
     * Crea el servicio buscando el courier por nombre.
     * Útil desde WarehouseDispatchController donde se tiene el nombre del courier.
     */
    public static function makeByName(string $courierName): CarrierServiceInterface
    {
        $courier = CourierCompany::where('name', $courierName)
            ->where('is_active', true)
            ->first();

        if (!$courier) {
            return new ManualCarrierService();
        }

        return self::make($courier);
    }

    /**
     * Lista de todos los drivers disponibles.
     */
    public static function availableDrivers(): array
    {
        return [
            'manual'    => 'Sin integración (tracking manual)',
            'chazki'    => 'Chazki',
            '99minutos' => '99Minutos',
        ];
    }
}
