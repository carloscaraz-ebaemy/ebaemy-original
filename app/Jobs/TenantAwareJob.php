<?php

namespace App\Jobs;

use App\Jobs\Middleware\SetTenantContext;
use Hyn\Tenancy\Environment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * TenantAwareJob — clase base para jobs que requieren contexto de tenant.
 *
 * Problema que resuelve:
 *   Los queue workers se inician sin tenant activo. Un job despachado desde
 *   el contexto del Tenant A, al ser ejecutado por el worker, no tiene la
 *   conexión de BD correcta → los modelos Eloquent leen/escriben en la BD
 *   equivocada o lanzan un error de conexión.
 *
 * Solución:
 *   Al crear el job (en el proceso con tenant activo), capturamos el UUID del
 *   website actual. Al ejecutar el job, el middleware SetTenantContext usa
 *   ese UUID para restaurar la conexión antes de llamar a handle().
 *
 * Uso:
 *   class MyJob extends TenantAwareJob implements ShouldQueue
 *   {
 *       public function handle(): void { ... }
 *   }
 *
 *   // Despachar desde un request (tenant activo):
 *   MyJob::dispatch();
 *
 * El UUID se captura automáticamente en el constructor — no hace falta
 * pasarlo manualmente.
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * UUID del website/tenant en cuyo contexto se debe ejecutar el job.
     * Se captura automáticamente al momento del dispatch.
     *
     * NO usar `readonly`: al deserializar el job en el queue worker,
     * SerializesModels intenta re-asignar el valor y readonly properties
     * solo pueden setearse una vez → 'Cannot initialize readonly property
     * TenantAwareJob::$websiteUuid' (log confirmado).
     */
    public string $websiteUuid;

    public function __construct()
    {
        $this->websiteUuid = $this->captureCurrentTenantUuid();
    }

    /**
     * Registra el middleware que restaura el tenant antes de handle().
     */
    public function middleware(): array
    {
        return [new SetTenantContext($this->websiteUuid)];
    }

    /**
     * Obtiene el UUID del tenant activo en el momento del dispatch.
     *
     * @throws \RuntimeException si no hay tenant activo (no se puede despachar
     *   un TenantAwareJob desde un contexto sin tenant).
     */
    private function captureCurrentTenantUuid(): string
    {
        try {
            $website = app(Environment::class)->tenant();
        } catch (\Throwable) {
            $website = null;
        }

        if (!$website || !$website->uuid) {
            throw new \RuntimeException(
                'TenantAwareJob dispatched with no active tenant. ' .
                'Only dispatch from within a tenant request or after calling $tenancy->tenant($website).'
            );
        }

        return $website->uuid;
    }
}
