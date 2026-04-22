<?php

namespace App\Console\Commands;

use App\Models\System\MarketplaceLead;
use App\Services\System\MarketplaceOrderDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reintenta leads del marketplace central que quedaron en 'failed' o 'new'
 * por fallos transitorios (tenant momentáneamente down, red intermitente, etc.).
 *
 * Estrategia: backoff — solo reintenta leads creados hace al menos `--min-age`
 * minutos, hasta 5 intentos por lead (evita loops infinitos con leads que
 * tienen un problema permanente, p.ej. item borrado del tenant).
 *
 * Uso:
 *   php artisan marketplace:retry-failed-leads
 *   php artisan marketplace:retry-failed-leads --limit=20 --min-age=5 --dry-run
 *
 * Programado en Kernel::schedule cada 15 minutos.
 */
class RetryMarketplaceFailedLeads extends Command
{
    protected $signature = 'marketplace:retry-failed-leads
                            {--limit=50 : Cantidad máxima de leads a procesar por corrida}
                            {--min-age=2 : Minutos mínimos de antigüedad antes de reintentar}
                            {--max-attempts=5 : Reintentos máximos por lead antes de marcarlo como archivado}
                            {--dry-run : Listar sin ejecutar el dispatch}';

    protected $description = 'Reintenta leads fallidos del marketplace central (ebaemy.com/marketplace)';

    public function handle(MarketplaceOrderDispatcher $dispatcher): int
    {
        $limit       = max(1, (int) $this->option('limit'));
        $minAge      = max(0, (int) $this->option('min-age'));
        $maxAttempts = max(1, (int) $this->option('max-attempts'));
        $dry         = (bool) $this->option('dry-run');

        // Candidatos: leads 'failed' o 'new' con antigüedad suficiente.
        // Se ordenan asc por created_at para atender primero los más antiguos.
        $leads = MarketplaceLead::whereIn('status', ['failed', 'new'])
            ->where('created_at', '<=', now()->subMinutes($minAge))
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        if ($leads->isEmpty()) {
            $this->info('No hay leads pendientes de reintento.');
            return self::SUCCESS;
        }

        $this->info("Procesando {$leads->count()} lead(s)" . ($dry ? ' [DRY-RUN]' : ''));

        $ok = 0;
        $fail = 0;
        $archived = 0;

        foreach ($leads as $lead) {
            // retry_count se infiere de sync_error history — usamos un contador
            // explícito en `retry_count` si existe, si no usamos 0.
            $attempts = (int) ($lead->retry_count ?? 0);

            if ($attempts >= $maxAttempts) {
                $msg = "Lead #{$lead->id} alcanzó {$attempts} intentos — archivado";
                $this->warn("  ⚠ {$msg}");
                if (!$dry) {
                    $lead->update([
                        'status'     => 'archived',
                        'sync_error' => "Auto-archivado tras {$attempts} reintentos fallidos",
                    ]);
                }
                $archived++;
                continue;
            }

            if ($dry) {
                $this->line("  · [dry] Lead #{$lead->id} tenant={$lead->tenant_fqdn} status={$lead->status} attempts={$attempts}");
                continue;
            }

            try {
                // retry_count se incrementa ANTES del dispatch para que si
                // el proceso crashea, no perdamos el conteo.
                $lead->retry_count = $attempts + 1;
                $lead->save();

                if ($dispatcher->dispatchLead($lead)) {
                    $this->line("  ✓ Lead #{$lead->id} → tenant {$lead->tenant_fqdn}");
                    $ok++;
                } else {
                    $this->line("  ✗ Lead #{$lead->id} falló de nuevo: {$lead->fresh()->sync_error}");
                    $fail++;
                }
            } catch (\Throwable $e) {
                Log::error('marketplace:retry-failed-leads error inesperado', [
                    'lead_id' => $lead->id,
                    'error'   => $e->getMessage(),
                ]);
                $this->error("  ✗ Lead #{$lead->id} excepción: {$e->getMessage()}");
                $fail++;
            }
        }

        $this->newLine();
        $this->info("OK: {$ok} · Fallos: {$fail} · Archivados: {$archived}");

        return self::SUCCESS;
    }
}
