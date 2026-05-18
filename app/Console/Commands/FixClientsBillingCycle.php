<?php

namespace App\Console\Commands;

use App\Models\System\Client;
use App\Models\System\PlanPeriod;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Repara clients.ending_billing_cycle para tenants creados con el bug
 * histórico de TenantCreationService (ending = mismo día de creación).
 *
 * Ese bug dejaba a los tenants nacidos "ya vencidos": en la siguiente
 * medianoche el cron PaymentOrderCommand::verifiedOrder() los marcaba
 * locked_tenant=true, devolviendo 403 "Su cuenta está inactiva" al
 * acceder al subdominio.
 *
 * Este comando extiende ending_billing_cycle a hoy + N meses (según
 * plan_period del cliente, 1 mes default) solo para clientes que están
 * desbloqueados (locked_tenant=0) y con la fecha vencida o vigente hoy.
 * No toca clientes con la fecha en el futuro ni clientes ya bloqueados
 * manualmente — esos los maneja el SuperAdmin caso a caso.
 *
 * Uso:
 *   php artisan clients:fix-billing-cycle              # aplica fix
 *   php artisan clients:fix-billing-cycle --dry-run    # solo reporta
 *   php artisan clients:fix-billing-cycle --only=ycre  # un tenant
 */
class FixClientsBillingCycle extends Command
{
    protected $signature = 'clients:fix-billing-cycle
                            {--dry-run : Solo reporta lo que actualizaría, no escribe}
                            {--only= : Subdominio del tenant a procesar (sin .ebaemy.com)}';

    protected $description = 'Extiende ending_billing_cycle vencido a hoy + N meses del plan_period (fix del bug histórico)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only   = $this->option('only');

        $query = Client::query()
            ->where('locked_tenant', 0)
            ->whereNotNull('ending_billing_cycle')
            ->whereDate('ending_billing_cycle', '<=', now()->toDateString());

        if ($only) {
            $only = strtolower(trim($only));
            $query->whereHas('hostname', function ($q) use ($only) {
                $q->where('fqdn', 'like', $only . '.%');
            });
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            $this->info('No hay clientes con ending_billing_cycle vencido para corregir.');
            return 0;
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Procesando {$clients->count()} cliente(s)...");
        $this->newLine();

        $stats = ['fixed' => 0, 'errors' => 0];

        foreach ($clients as $client) {
            $fqdn = optional($client->hostname)->fqdn ?? '(sin hostname)';

            try {
                $planMonths = 1;
                if ($client->plan_period_id) {
                    $period = PlanPeriod::find($client->plan_period_id);
                    if ($period && (int) $period->months > 0) {
                        $planMonths = (int) $period->months;
                    }
                }

                $oldEnding = optional($client->ending_billing_cycle)->toDateString() ?? '(null)';
                $newEnding = Carbon::now()->addMonths($planMonths)->toDateString();

                $this->line("  • {$fqdn}: {$oldEnding} → {$newEnding} (+{$planMonths} mes" . ($planMonths > 1 ? 'es' : '') . ')');

                if (!$dryRun) {
                    $client->ending_billing_cycle = $newEnding;
                    $client->save();
                }

                $stats['fixed']++;
            } catch (\Throwable $e) {
                $this->error("  ✗ {$fqdn}: error — " . $e->getMessage());
                $stats['errors']++;
            }
        }

        $this->newLine();
        $this->info('Resumen:');
        $this->line('  ' . ($dryRun ? 'A corregir' : 'Corregidos') . ":  {$stats['fixed']}");
        $this->line("  Errores:     {$stats['errors']}");

        return $stats['errors'] > 0 ? 1 : 0;
    }
}
