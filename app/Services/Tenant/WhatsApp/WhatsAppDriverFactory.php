<?php

namespace App\Services\Tenant\WhatsApp;

use App\Models\Tenant\Configuration;
use App\Models\Tenant\ConfigurationEcommerce;
use App\Services\Tenant\WhatsApp\Contracts\WhatsAppDriverInterface;
use App\Services\Tenant\WhatsApp\Drivers\MetaCloudDriver;
use App\Services\Tenant\WhatsApp\Drivers\NoneDriver;
use App\Services\Tenant\WhatsApp\Drivers\QrApiDriver;

/**
 * Factory que elige el driver de WhatsApp activo según la configuración
 * del tenant actual.
 *
 * Orden de prioridad (primero que esté configurado gana):
 *   1. Driver preferido explícito (configuration_ecommerce.whatsapp_driver)
 *   2. QR API (configurations.qr_api_enable + URL + apiKey)
 *   3. Meta Cloud (configuration_ecommerce.whatsapp_api_token + phone_id)
 *   4. None (no-op)
 *
 * Uso:
 *   $driver = WhatsAppDriverFactory::make();
 *   $driver->send('51999999999', 'Hola');
 */
class WhatsAppDriverFactory
{
    /**
     * Retorna el driver activo para el tenant actual.
     *
     * @param  string|null  $forceDriver  Fuerza un driver específico (para tests):
     *                                    'meta_cloud', 'qr_api', 'none'.
     */
    public static function make(?string $forceDriver = null): WhatsAppDriverInterface
    {
        if ($forceDriver) {
            return self::buildDriver($forceDriver);
        }

        $ecomConfig = ConfigurationEcommerce::first();
        $preferredDriver = $ecomConfig->whatsapp_driver ?? null;

        // 1. Si el tenant eligió explícitamente un driver, respetarlo si está configurado
        if ($preferredDriver) {
            $driver = self::buildDriver($preferredDriver);
            if ($driver->isConfigured()) {
                return $driver;
            }
        }

        // 2. QR API primero (compatibilidad con config actual del sistema)
        $qrApi = new QrApiDriver();
        if ($qrApi->isConfigured()) {
            return $qrApi;
        }

        // 3. Meta Cloud
        $metaCloud = new MetaCloudDriver($ecomConfig);
        if ($metaCloud->isConfigured()) {
            return $metaCloud;
        }

        // 4. Ninguno
        return new NoneDriver();
    }

    /**
     * Construye un driver por su nombre.
     */
    public static function buildDriver(string $name): WhatsAppDriverInterface
    {
        return match ($name) {
            'meta_cloud' => new MetaCloudDriver(),
            'qr_api'     => new QrApiDriver(),
            'none'       => new NoneDriver(),
            default      => new NoneDriver(),
        };
    }

    /**
     * Lista todos los drivers disponibles con su estado de configuración
     * (útil para el panel admin).
     *
     * @return array<array{name: string, configured: bool, label: string}>
     */
    public static function availableDrivers(): array
    {
        return [
            [
                'name'       => 'meta_cloud',
                'label'      => 'Meta Cloud API (oficial)',
                'configured' => (new MetaCloudDriver())->isConfigured(),
                'description'=> '1000 conversaciones gratis/mes. Requiere Facebook Business Manager.',
            ],
            [
                'name'       => 'qr_api',
                'label'      => 'QR API (WhatsApp-Web)',
                'configured' => (new QrApiDriver())->isConfigured(),
                'description'=> 'Gateway tipo WhatsApp-Web. Gratis pero no oficial.',
            ],
            [
                'name'       => 'none',
                'label'      => 'Deshabilitado',
                'configured' => true,
                'description'=> 'No envía mensajes (útil para tenants sin WhatsApp configurado).',
            ],
        ];
    }
}
