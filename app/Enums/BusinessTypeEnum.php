<?php

namespace App\Enums;

/**
 * BusinessTypeEnum — Rubro del negocio por tenant.
 *
 * Centraliza la lógica que antes estaba dispersa como:
 *   $user->hasModule('restaurant')
 *   $user->hasModule('ecommerce')
 *   if ($config->is_pharmacy) ...
 *
 * Uso recomendado:
 *   app(TenantContextService::class)->isRestaurant()
 *   app(TenantContextService::class)->getBusinessType()->requiredModules()
 */
enum BusinessTypeEnum: string
{
    case RETAIL     = 'retail';
    case RESTAURANT = 'restaurant';
    case ECOMMERCE  = 'ecommerce';
    case SERVICES   = 'services';
    case LOGISTICS  = 'logistics';
    case EDUCATION  = 'education';

    public function label(): string
    {
        return match($this) {
            self::RETAIL     => 'Retail / Tienda',
            self::RESTAURANT => 'Restaurante / POS',
            self::ECOMMERCE  => 'Tienda Online',
            self::SERVICES   => 'Servicios',
            self::LOGISTICS  => 'Logística',
            self::EDUCATION  => 'Educación',
        };
    }

    /**
     * Módulos que deben estar activos por defecto para este rubro.
     * Usado al crear un tenant nuevo o al cambiar de rubro.
     */
    public function requiredModules(): array
    {
        return match($this) {
            self::RETAIL     => ['documents', 'inventory', 'persons'],
            self::RESTAURANT => ['documents', 'restaurant_app', 'pos'],
            self::ECOMMERCE  => ['documents', 'ecommerce', 'inventory'],
            self::SERVICES   => ['documents', 'sale', 'persons'],
            self::LOGISTICS  => ['documents', 'logistic', 'inventory', 'guia'],
            self::EDUCATION  => ['documents', 'sale', 'persons'],
        };
    }

    /**
     * Indica si el rubro requiere control de despacho (warehouse queue).
     */
    public function requiresWarehouseDispatch(): bool
    {
        return match($this) {
            self::ECOMMERCE,
            self::LOGISTICS,
            self::RETAIL    => true,
            default         => false,
        };
    }

    /**
     * Indica si el rubro tiene módulo ecommerce habilitado.
     */
    public function hasEcommerce(): bool
    {
        return $this === self::ECOMMERCE;
    }

    /**
     * Devuelve todos los valores como array de strings.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Devuelve opciones para selects (value => label).
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->all();
    }
}
