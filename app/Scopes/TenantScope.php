<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * TenantScope — Global Scope para aislamiento de datos por tenant.
 *
 * Filtra automáticamente queries por el tenant_id del contexto actual.
 * Se aplica via el trait BelongsToTenant.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = tenant_id();

        if ($tenantId) {
            $column = method_exists($model, 'getTenantColumn')
                ? $model->getTenantColumn()
                : 'tenant_id';

            $builder->where(
                $model->qualifyColumn($column),
                $tenantId
            );
        }
    }
}
