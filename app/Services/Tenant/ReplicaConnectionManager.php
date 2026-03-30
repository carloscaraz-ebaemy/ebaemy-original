<?php

namespace App\Services\Tenant;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * ReplicaConnectionManager
 *
 * Administra la conexión de solo-lectura (réplica MySQL) para el tenant activo.
 *
 * Cuando TENANT_REPLICA_HOST está configurado en .env y el listener
 * SetupReplicaConnection ha registrado la conexión 'tenant_read',
 * este servicio devuelve esa conexión.
 * Si no hay réplica configurada, devuelve la conexión primaria ('tenant').
 *
 * Uso en código:
 *
 *   // Query builder directo
 *   app(ReplicaConnectionManager::class)->table('sale_notes')->where(...)->get();
 *
 *   // Query builder a partir de un modelo Eloquent
 *   app(ReplicaConnectionManager::class)->queryFor(SaleNote::class)->where(...)->get();
 *
 *   // Macro de fachada DB (registrado en AppServiceProvider)
 *   DB::replica()->table('sale_notes')->...
 *
 * Configuración .env:
 *   TENANT_REPLICA_HOST=replica.mysql.internal   # activa réplica
 *   TENANT_REPLICA_PORT=3306                      # opcional, hereda DB_PORT si se omite
 */
class ReplicaConnectionManager
{
    public const REPLICA_CONNECTION = 'tenant_read';
    public const PRIMARY_CONNECTION = 'tenant';

    /**
     * ¿Está la réplica activa en esta request?
     * (El listener SetupReplicaConnection la registra cuando TENANT_REPLICA_HOST está definido.)
     */
    public function isEnabled(): bool
    {
        return !empty(env('TENANT_REPLICA_HOST'))
            && config('database.connections.' . self::REPLICA_CONNECTION) !== null;
    }

    /**
     * Devuelve la conexión de solo-lectura si está disponible, o la primaria.
     */
    public function connection(): ConnectionInterface
    {
        return DB::connection($this->connectionName());
    }

    /**
     * Nombre de la conexión activa (tenant_read o tenant).
     */
    public function connectionName(): string
    {
        return $this->isEnabled() ? self::REPLICA_CONNECTION : self::PRIMARY_CONNECTION;
    }

    /**
     * Devuelve un QueryBuilder apuntando a la tabla del modelo dado, usando réplica si
     * está disponible, o la conexión primaria del modelo si no.
     *
     * @param  string  $modelClass  FQCN del modelo Eloquent (ej: SaleNote::class)
     * @return \Illuminate\Database\Query\Builder
     */
    public function queryFor(string $modelClass): \Illuminate\Database\Query\Builder
    {
        /** @var \Illuminate\Database\Eloquent\Model $instance */
        $instance = new $modelClass;

        if ($this->isEnabled()) {
            return DB::connection(self::REPLICA_CONNECTION)->table($instance->getTable());
        }

        return $instance->newQuery()->toBase();
    }

    /**
     * Shortcut para query directa sobre una tabla.
     */
    public function table(string $table): \Illuminate\Database\Query\Builder
    {
        return $this->connection()->table($table);
    }
}
