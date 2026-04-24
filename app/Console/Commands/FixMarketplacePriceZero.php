<?php

namespace App\Console\Commands;

use App\Models\System\Client;
use App\Services\System\MarketplaceListingSyncService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Limpieza one-shot: convierte mp_price=0 → NULL en ambos lados
 * (marketplace_listings central + items de cada tenant).
 *
 * Antes, el form del tenant permitía guardar 0 cuando el usuario borraba
 * el input "precio en marketplace" y Element UI devolvía 0 en lugar de null.
 * Ese 0 ganaba sobre el precio normal por culpa del operador ??, y el
 * producto aparecía como S/0.00 en ebaemy.com/marketplace aunque tenía
 * precio válido.
 *
 * Uso:
 *   php artisan marketplace:fix-zero-prices          # dry-run por defecto
 *   php artisan marketplace:fix-zero-prices --apply  # ejecuta los updates
 */
class FixMarketplacePriceZero extends Command
{
    protected $signature   = 'marketplace:fix-zero-prices {--apply : Ejecutar los updates (sin esto solo reporta)}';
    protected $description = 'Limpia mp_price=0 → NULL en marketplace_listings y en items de cada tenant';

    public function handle()
    {
        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Modo APPLY — se harán updates.' : 'Modo DRY-RUN — no se modifica nada.');
        $this->line('');

        // 1) marketplace_listings central
        $centralAffected = DB::connection('system')->table('marketplace_listings')
            ->where('mp_price', 0)
            ->count();
        $this->line("Central marketplace_listings con mp_price=0: <comment>{$centralAffected}</comment>");

        if ($apply && $centralAffected > 0) {
            $updated = DB::connection('system')->table('marketplace_listings')
                ->where('mp_price', 0)
                ->update(['mp_price' => null]);
            $this->info("  → actualizados: {$updated}");
        }

        // 2) items de cada tenant
        $hostnames = Hostname::with('website')->get();
        $this->line('');
        $this->line("Recorriendo " . $hostnames->count() . " tenants para corregir items.mp_price…");

        $tenancy   = app(Environment::class);
        $originalT = $tenancy->tenant();
        $tenantTotal = 0;
        $resyncIds = []; // [hostname_id => [item_ids]]

        foreach ($hostnames as $hn) {
            if (!$hn->website) continue;
            try {
                $tenancy->tenant($hn->website);

                $zeros = DB::connection('tenant')->table('items')
                    ->where('mp_price', 0)
                    ->pluck('id')
                    ->all();

                $count = count($zeros);
                if ($count === 0) continue;

                $this->line("  {$hn->fqdn}: {$count} items con mp_price=0");
                $tenantTotal += $count;

                if ($apply) {
                    DB::connection('tenant')->table('items')
                        ->whereIn('id', $zeros)
                        ->update(['mp_price' => null]);
                    $resyncIds[$hn->id] = $zeros;
                }
            } catch (\Throwable $e) {
                $this->warn("  {$hn->fqdn}: error — " . $e->getMessage());
            }
        }

        $tenancy->tenant($originalT ?: null);
        $this->line("Total items de tenants afectados: <comment>{$tenantTotal}</comment>");

        // 3) Resync del listing central para los publicables (para refrescar display_price)
        if ($apply && !empty($resyncIds)) {
            $this->line('');
            $this->line('Resincronizando listings publicables…');
            $sync = app(MarketplaceListingSyncService::class);
            $synced = 0;
            foreach ($resyncIds as $hostnameId => $ids) {
                foreach ($ids as $id) {
                    try {
                        $sync->syncItem((int) $hostnameId, (int) $id);
                        $synced++;
                    } catch (\Throwable $e) {
                        // seguir con los demás
                    }
                }
            }
            $this->info("  → {$synced} items resincronizados al índice central");
        }

        $this->line('');
        $this->info($apply ? 'Listo.' : 'Dry-run terminado. Corre con --apply para ejecutar.');

        return 0;
    }
}
