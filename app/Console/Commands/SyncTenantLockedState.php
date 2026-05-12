<?php

namespace App\Console\Commands;

use App\Models\System\Client;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sincroniza el campo locked_tenant entre system.clients y la tabla
 * tenant.configurations de cada tenant.
 *
 * Problema: el toggle "Bloquear cuenta" del SuperAdmin solo propaga
 * el cambio cuando se mueve. Si nunca se mueve después de crear el
 * tenant, system.clients.locked_tenant puede quedar en false (default)
 * pero la tabla tenant.configurations.locked_tenant puede haber quedado
 * en true (estado inicial al provisionar). Resultado: el dueño del
 * tenant entra y recibe 403 'Acceso Denegado' aunque en system el
 * cliente aparezca como desbloqueado.
 *
 * Uso:
 *   php artisan tenants:sync-locked              # corre el sync
 *   php artisan tenants:sync-locked --dry-run    # solo reporta, no cambia
 *   php artisan tenants:sync-locked --only=motalvan  # un tenant específico
 */
class SyncTenantLockedState extends Command
{
    protected $signature = 'tenants:sync-locked
                            {--dry-run : Solo reporta divergencias, no actualiza}
                            {--only= : Subdominio del tenant a sincronizar (sin .ebaemy.com)}';

    protected $description = 'Sincroniza clients.locked_tenant -> tenant.configurations.locked_tenant';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only   = $this->option('only');

        $query = Website::query();
        if ($only) {
            // Filtramos vía hostname → website.
            $hostnameId = DB::connection('system')->table('hostnames')
                ->where('fqdn', 'like', strtolower($only) . '.%')
                ->value('website_id');
            if (!$hostnameId) {
                $this->error("No se encontró tenant para subdomain '{$only}'.");
                return 1;
            }
            $query->where('id', $hostnameId);
        }

        $websites = $query->get();
        if ($websites->isEmpty()) {
            $this->warn('No hay tenants para procesar.');
            return 0;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Procesando {$websites->count()} tenant(s)...");
        $this->newLine();

        $stats = ['ok' => 0, 'fixed' => 0, 'no_config' => 0, 'errors' => 0];

        foreach ($websites as $website) {
            $hostname = $website->hostnames()->first();
            $fqdn = $hostname->fqdn ?? '(sin hostname)';

            $client = Client::query()
                ->where('hostname_id', $hostname->id ?? null)
                ->first();

            if (!$client) {
                $this->warn("  ✗ {$fqdn}: sin Client en system.clients — saltado.");
                $stats['errors']++;
                continue;
            }

            $systemLocked = (bool) $client->locked_tenant;

            try {
                $env = app(\Hyn\Tenancy\Environment::class);
                $env->tenant($website);

                $tenantCfg = DB::connection('tenant')->table('configurations')
                    ->where('id', 1)->first(['locked_tenant']);

                if (!$tenantCfg) {
                    $this->warn("  ✗ {$fqdn}: no existe configurations.id=1 en BD tenant — saltado.");
                    $stats['no_config']++;
                    continue;
                }

                $tenantLocked = (bool) $tenantCfg->locked_tenant;

                if ($systemLocked === $tenantLocked) {
                    $this->line("  ✓ {$fqdn}: en sync (locked=" . ($systemLocked ? 'true' : 'false') . ')');
                    $stats['ok']++;
                    continue;
                }

                $this->warn("  ⚠ {$fqdn}: DIVERGENCIA system=" . ($systemLocked ? 'true' : 'false')
                          . " vs tenant=" . ($tenantLocked ? 'true' : 'false'));

                if (!$dryRun) {
                    DB::connection('tenant')->table('configurations')
                        ->where('id', 1)
                        ->update(['locked_tenant' => $systemLocked]);
                    $this->info("    → tenant.configurations.locked_tenant = " . ($systemLocked ? 'true' : 'false'));
                }

                $stats['fixed']++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$fqdn}: error — " . $e->getMessage());
                $stats['errors']++;
            }
        }

        $this->newLine();
        $this->info('Resumen:');
        $this->line("  En sync:           {$stats['ok']}");
        $this->line('  ' . ($dryRun ? 'Detectados a corregir' : 'Corregidos') . ":   {$stats['fixed']}");
        $this->line("  Sin config row:    {$stats['no_config']}");
        $this->line("  Errores:           {$stats['errors']}");

        return $stats['errors'] > 0 ? 1 : 0;
    }
}
