<?php

namespace App\Listeners\Tenancy;

use App\Services\Tenant\ReplicaConnectionManager;
use Hyn\Tenancy\Events\Database\ConnectionSet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Registra la conexión de solo-lectura 'tenant_read' apuntando a la réplica MySQL,
 * justo después de que hyn/tenancy configura la conexión primaria 'tenant'.
 *
 * Se activa solo si TENANT_REPLICA_HOST está definido en .env.
 * Si la réplica no está disponible, la app opera normalmente sobre la primaria.
 *
 * Configuración .env:
 *   TENANT_REPLICA_HOST=replica.internal        # host de la réplica
 *   TENANT_REPLICA_PORT=3306                     # puerto (opcional)
 *
 * La conexión 'tenant_read':
 *   - Mismo usuario/contraseña que 'tenant' (generados por hyn/tenancy)
 *   - Apunta al host de réplica en lugar del primario
 *   - Se recrea en cada cambio de tenant (cada request)
 */
class SetupReplicaConnection
{
    public function handle(ConnectionSet $event): void
    {
        // Solo actuar sobre la conexión tenant, no system
        if ($event->connection !== 'tenant') {
            return;
        }

        $replicaHost = env('TENANT_REPLICA_HOST');
        if (empty($replicaHost)) {
            return;
        }

        $tenantConfig = config('database.connections.' . ReplicaConnectionManager::PRIMARY_CONNECTION);

        if (!is_array($tenantConfig) || empty($tenantConfig['host'])) {
            return;
        }

        $replicaPort = (int) env('TENANT_REPLICA_PORT', $tenantConfig['port'] ?? 3306);

        $replicaConfig = array_merge($tenantConfig, [
            'host'    => $replicaHost,
            'port'    => $replicaPort,
            // sticky: lecturas post-escritura en la misma request van al primario
            'sticky'  => false,
        ]);

        config(['database.connections.' . ReplicaConnectionManager::REPLICA_CONNECTION => $replicaConfig]);

        // Purgar cualquier conexión anterior para este tenant
        DB::purge(ReplicaConnectionManager::REPLICA_CONNECTION);

        Log::debug('[SetupReplicaConnection] Conexión réplica registrada.', [
            'tenant' => optional($event->website)->uuid,
            'host'   => $replicaHost,
            'port'   => $replicaPort,
        ]);
    }
}
