<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * DEPRECADO — usar `stock:reconcile`.
 *
 * Este comando calculaba el stock del producto con un criterio propio (solo
 * variantes activas, sin mirar lo comprometido) distinto del que usaba
 * ItemVariantService::propagateStock() (todas las variantes). El resultado era
 * que "corregía" valores que el siguiente guardado del producto volvía a
 * cambiar, y el stock de un producto con variantes oscilaba según qué se
 * hubiera ejecutado último.
 *
 * Ahora hay un único cálculo —ItemVariantService::computeStock()— y un único
 * comando que lo consume. Este se conserva solo para no romper crons o notas
 * que aún invoquen el nombre viejo: delega y avisa.
 */
class SyncVariantStock extends Command
{
    protected $signature = 'stock:sync-variants
                            {--check : Solo reportar sin corregir}
                            {--item= : Ignorado — el comando nuevo no filtra por código de producto}';

    protected $description = '[DEPRECADO] Usar stock:reconcile — delega en él';

    public function handle(): int
    {
        $this->warn('stock:sync-variants está deprecado.');
        $this->line('Usa el comando único de reconciliación:');
        $this->newLine();
        $this->line('  php artisan stock:reconcile                 # dry-run, todos los tenants');
        $this->line('  php artisan stock:reconcile --tenant=UUID   # un tenant');
        $this->line('  php artisan stock:reconcile --tenant=UUID --fix');
        $this->newLine();

        if ($this->option('item')) {
            $this->warn('La opción --item ya no existe; stock:reconcile revisa todos los productos.');
        }

        // A propósito NO delegamos con --fix. El comando viejo trabajaba sobre el
        // tenant activo; stock:reconcile recorre los 11 salvo que se le acote con
        // --tenant. Escribir en once bases porque alguien tecleó el nombre viejo
        // sería una sorpresa cara. Lanzamos solo el diagnóstico.
        return $this->call('stock:reconcile');
    }
}
