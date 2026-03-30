<?php

namespace App\Traits;

use App\Scopes\TenantScope;

/**
 * BelongsToTenant — Aislamiento automático de datos por tenant.
 *
 * Agrega Global Scope que filtra automáticamente por tenant_id.
 * Usar en modelos que necesitan aislamiento multi-tenant en BD compartida.
 *
 * Para modelos que usan UsesTenantConnection (BD separada por tenant),
 * el aislamiento ya está garantizado por la conexión. Este trait es
 * para modelos en BD compartida o tablas cross-tenant.
 *
 * Uso:
 *   class Order extends Model {
 *       use BelongsToTenant;
 *   }
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Aplicar scope global para filtrar por tenant
        static::addGlobalScope(new TenantScope());

        // Auto-asignar tenant_id al crear
        static::creating(function ($model) {
            if (!$model->getAttribute($model->getTenantColumn())) {
                $tenantId = tenant_id();
                if ($tenantId) {
                    $model->setAttribute($model->getTenantColumn(), $tenantId);
                }
            }
        });
    }

    /**
     * Nombre de la columna que almacena el tenant ID.
     * Override en el modelo si usa otro nombre.
     */
    public function getTenantColumn(): string
    {
        return 'tenant_id';
    }

    /**
     * Query sin filtro de tenant (para admins cross-tenant).
     */
    public static function withoutTenant(): \Illuminate\Database\Eloquent\Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
