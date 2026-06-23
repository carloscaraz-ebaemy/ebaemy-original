<?php

namespace App\Console\Commands;

use App\Models\Tenant\Item;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Hostname;
use Illuminate\Console\Command;

/**
 * Corrige códigos internos (internal_id) repetidos entre productos.
 *
 * Causa raíz: la función duplicate() del ItemController usaba ->replicate(), que
 * copia el internal_id del producto original (derivado de su id). El duplicado
 * heredaba el mismo código → dos o más items con el mismo internal_id.
 *
 * Lógica de corrección, por cada grupo de items que comparten un internal_id:
 *   - Se conserva el de menor id (el "original").
 *   - A los demás se les asigna un internal_id nuevo derivado de su propio id
 *     (str_pad(id, 5), mismo criterio que generateInternalId). Si ese código ya
 *     estuviera ocupado por otro item, se incrementa hasta encontrar uno libre.
 *
 * Uso:
 *   php artisan items:fix-duplicate-internal-id                   # todos los tenants
 *   php artisan items:fix-duplicate-internal-id --tenant=alasitas # un solo tenant
 *   php artisan items:fix-duplicate-internal-id --dry             # solo reporta, no escribe
 */
class FixDuplicateInternalId extends Command
{
    protected $signature = 'items:fix-duplicate-internal-id
        {--tenant= : UUID o nombre corto del tenant (ej. alasitas)}
        {--dry : No escribir nada (preview en consola)}';

    protected $description = 'Reasigna un código interno único a los productos con internal_id repetido';

    public function handle(): int
    {
        $tenantFilter = $this->option('tenant');
        $dry          = (bool) $this->option('dry');

        $hostnames = Hostname::with('website')->get();
        if ($tenantFilter) {
            $hostnames = $hostnames->filter(function ($hn) use ($tenantFilter) {
                if (!$hn->website) return false;
                return str_contains($hn->website->uuid, $tenantFilter) || str_contains($hn->fqdn, $tenantFilter);
            });
            if ($hostnames->isEmpty()) {
                $this->error("No se encontró tenant '{$tenantFilter}'");
                return 1;
            }
        }

        $this->info('Corrección de códigos internos repetidos — ' . $hostnames->count()
            . ' tenant(s) — modo: ' . ($dry ? 'DRY (sin escribir)' : 'aplicar'));
        $this->line('');

        $tenancy   = app(Environment::class);
        $originalT = $tenancy->tenant();
        $grandTotal = 0;

        foreach ($hostnames as $hn) {
            if (!$hn->website) continue;

            try {
                $tenancy->tenant($hn->website);
                $fixed = $this->processTenant($hn->fqdn, $dry);
                $grandTotal += $fixed;
            } catch (\Throwable $e) {
                $this->error("  {$hn->fqdn}: ERROR — " . $e->getMessage());
            }
        }

        $tenancy->tenant($originalT ?: null);

        $this->line('');
        $this->info('═══ TOTAL ═══');
        $this->line(($dry ? 'Códigos que se reasignarían: ' : 'Códigos reasignados: ')
            . "<comment>{$grandTotal}</comment>");

        return 0;
    }

    private function processTenant(string $fqdn, bool $dry): int
    {
        // Grupos de internal_id no vacíos con más de un item.
        $dupCodes = Item::query()
            ->whereNotNull('internal_id')
            ->where('internal_id', '!=', '')
            ->selectRaw('internal_id, COUNT(*) as c')
            ->groupBy('internal_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('c', 'internal_id');

        if ($dupCodes->isEmpty()) {
            $this->line("  {$fqdn}: sin duplicados.");
            return 0;
        }

        // Conjunto de todos los internal_id ocupados (para evitar nuevas colisiones).
        $used = Item::whereNotNull('internal_id')->where('internal_id', '!=', '')
            ->pluck('internal_id')->flip();

        $fixed = 0;
        $this->warn("  {$fqdn}: {$dupCodes->count()} código(s) repetido(s).");

        foreach ($dupCodes->keys() as $code) {
            // Todos los items con ese código, el menor id primero (original).
            $items = Item::where('internal_id', $code)->orderBy('id')->get(['id', 'internal_id', 'description']);

            // El primero conserva el código; al resto se le asigna uno nuevo.
            foreach ($items->slice(1) as $item) {
                $newCode = $this->nextFreeCode($item->id, $used);
                $used->put($newCode, true);

                $this->line("    #{$item->id} \"{$item->description}\": {$code} → {$newCode}");

                if (!$dry) {
                    $item->internal_id = $newCode;
                    $item->save();
                }
                $fixed++;
            }
        }

        return $fixed;
    }

    /**
     * Código derivado del id (str_pad a 5). Si ya está ocupado, incrementa hasta
     * encontrar uno libre.
     */
    private function nextFreeCode(int $id, \Illuminate\Support\Collection $used): string
    {
        $candidate = $id;
        do {
            $code = str_pad((string) $candidate, 5, '0', STR_PAD_LEFT);
            $candidate++;
        } while ($used->has($code));

        return $code;
    }
}
