<?php

namespace App\Traits;

use App\Models\Tenant\AuditLog;

trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            $module = static::auditModule();
            AuditLog::record('create', $module, "Creado: {$module}", $model, null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $dirty = $model->getDirty();
            if (empty($dirty)) return;

            $module = static::auditModule();
            $old = array_intersect_key($model->getOriginal(), $dirty);
            AuditLog::record('update', $module, "Actualizado: {$module}", $model, $old, $dirty);
        });

        static::deleted(function ($model) {
            $module = static::auditModule();
            AuditLog::record('delete', $module, "Eliminado: {$module} #{$model->id}", $model, $model->getAttributes());
        });
    }

    protected static function auditModule(): string
    {
        // Override in model if needed. Default: snake_case of class name
        return str_replace('_', '-', \Illuminate\Support\Str::snake(class_basename(static::class)));
    }
}
