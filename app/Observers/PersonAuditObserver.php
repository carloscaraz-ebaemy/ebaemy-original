<?php

namespace App\Observers;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Person;

/**
 * Deja rastro en `audit_logs` del alta y de los cambios de datos de un
 * cliente o proveedor. Lo lee la Vista 360° del cliente (pestaña Registro).
 *
 * Solo se guardan los campos que cambiaron, con su valor anterior y el nuevo.
 * Las marcas de tiempo no cuentan como cambio de datos.
 */
class PersonAuditObserver
{
    /** Campos que no son datos del cliente o que no deben quedar escritos. */
    private const IGNORED = [
        'created_at', 'updated_at', 'password', 'remember_token', 'api_token',
    ];

    public function created(Person $person): void
    {
        $this->safely(fn () => AuditLog::record(
            'create',
            'persons',
            "Registro: {$person->name}",
            $person,
            null,
            $this->clean($person->getAttributes())
        ));
    }

    public function updated(Person $person): void
    {
        $changed = $this->clean($person->getChanges());

        if (!$changed) {
            return;
        }

        $this->safely(fn () => AuditLog::record(
            'update',
            'persons',
            "Datos modificados: {$person->name}",
            $person,
            array_intersect_key($person->getOriginal(), $changed),
            $changed
        ));
    }

    public function deleted(Person $person): void
    {
        $this->safely(fn () => AuditLog::record(
            'delete',
            'persons',
            "Eliminado: {$person->name}",
            $person,
            $this->clean($person->getAttributes())
        ));
    }

    private function clean(array $attributes): array
    {
        return array_diff_key($attributes, array_flip(self::IGNORED));
    }

    /** La bitacora nunca puede impedir que se guarde un cliente. */
    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            \Log::warning('[PersonAuditObserver] No se pudo auditar el cambio.', ['error' => $e->getMessage()]);
        }
    }
}
