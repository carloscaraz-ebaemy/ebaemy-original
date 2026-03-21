<?php

namespace App\Services\Tenant;

use App\Enums\BusinessTypeEnum;
use App\Models\Tenant\Configuration;

/**
 * TenantContextService
 *
 * Punto único de verdad sobre el contexto del tenant actual.
 * Reemplaza los `if ($user->hasModule('restaurant'))` dispersos
 * por métodos semánticos y centralizados.
 *
 * Registrado como singleton en AppServiceProvider → una sola query por request.
 *
 * ANTES (disperso en 15+ controladores):
 *   if ($user->hasModule('restaurant')) { ... }
 *   if ($user->hasModule('ecommerce'))  { ... }
 *
 * DESPUÉS (un punto central):
 *   if (app(TenantContextService::class)->isRestaurant()) { ... }
 *   if (tenantCtx()->isEcommerce()) { ... }  // con helper global
 */
class TenantContextService
{
    private ?Configuration $config = null;
    private ?BusinessTypeEnum $type = null;

    /**
     * Devuelve el tipo de negocio del tenant actual.
     */
    public function getBusinessType(): BusinessTypeEnum
    {
        if ($this->type === null) {
            $raw = $this->getConfig()->business_type;

            // Si el cast del enum ya lo resolvió, usarlo directamente
            $this->type = $raw instanceof BusinessTypeEnum
                ? $raw
                : BusinessTypeEnum::tryFrom((string) $raw) ?? BusinessTypeEnum::RETAIL;
        }

        return $this->type;
    }

    public function is(BusinessTypeEnum $type): bool
    {
        return $this->getBusinessType() === $type;
    }

    public function isRetail(): bool     { return $this->is(BusinessTypeEnum::RETAIL);     }
    public function isRestaurant(): bool { return $this->is(BusinessTypeEnum::RESTAURANT); }
    public function isEcommerce(): bool  { return $this->is(BusinessTypeEnum::ECOMMERCE);  }
    public function isServices(): bool   { return $this->is(BusinessTypeEnum::SERVICES);   }
    public function isLogistics(): bool  { return $this->is(BusinessTypeEnum::LOGISTICS);  }
    public function isEducation(): bool  { return $this->is(BusinessTypeEnum::EDUCATION);  }

    /**
     * Indica si el rubro activo tiene despacho a almacén.
     */
    public function requiresWarehouseDispatch(): bool
    {
        return $this->getBusinessType()->requiresWarehouseDispatch();
    }

    /**
     * Indica si el rubro activo tiene ecommerce.
     */
    public function hasEcommerce(): bool
    {
        return $this->getBusinessType()->hasEcommerce();
    }

    /**
     * Devuelve la etiqueta del rubro actual para mostrar en UI.
     */
    public function label(): string
    {
        return $this->getBusinessType()->label();
    }

    /**
     * Fuerza recarga de la configuración (útil si se cambia el rubro en runtime).
     */
    public function refresh(): static
    {
        $this->config = null;
        $this->type   = null;
        return $this;
    }

    private function getConfig(): Configuration
    {
        return $this->config ??= Configuration::select('id', 'business_type')->first()
            ?? new Configuration(['business_type' => 'retail']);
    }
}
