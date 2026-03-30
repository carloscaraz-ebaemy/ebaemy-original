<?php

namespace App\Jobs\Middleware;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Support\Facades\Log;

/**
 * Job middleware que restaura el contexto del tenant antes de ejecutar el job.
 *
 * Uso: retornar esta clase desde TenantAwareJob::middleware().
 *
 * El queue worker corre en un proceso sin tenant activo; este middleware
 * carga el tenant por UUID y activa la conexión correspondiente para que
 * los modelos Eloquent usen la BD correcta.
 */
class SetTenantContext
{
    public function __construct(private string $websiteUuid)
    {
    }

    public function handle(mixed $job, \Closure $next): void
    {
        $website = Website::where('uuid', $this->websiteUuid)->first();

        if (!$website) {
            Log::error("TenantAwareJob: website UUID [{$this->websiteUuid}] not found, aborting job.", [
                'job' => get_class($job),
            ]);
            $job->fail(new \RuntimeException("Tenant [{$this->websiteUuid}] not found."));
            return;
        }

        /** @var Environment $tenancy */
        $tenancy = app(Environment::class);
        $tenancy->tenant($website);

        // Cada TenantAwareJob establece su propio contexto antes de ejecutarse,
        // así que no es necesario resetear al finalizar — el siguiente job
        // hará su propio switch. Llamar tenant() sin argumento es un getter,
        // no un setter, así que no hay forma limpia de "limpiar" el contexto.
        $next($job);
    }
}
