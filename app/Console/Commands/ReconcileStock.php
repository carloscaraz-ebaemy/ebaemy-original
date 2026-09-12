<?php

namespace App\Console\Commands;

use App\Models\Tenant\Item;
use App\Services\Tenant\ItemVariantService;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciliación de stock de productos con variantes — comando ÚNICO.
 *
 * Compara lo guardado en items.stock / item_warehouse contra el cálculo
 * canónico de ItemVariantService::computeStock(), y al corregir invoca
 * propagateStock(): exactamente la misma función que escribe en producción
 * cuando se edita una variante. Por construcción, el comando no puede tener
 * una idea del stock distinta de la del resto del sistema.
 *
 * Sustituye a stock:sync-variants, que hacía la misma cuenta con otro criterio
 * (ignoraba lo comprometido y contaba solo las activas) y por eso "corregía"
 * valores que el siguiente guardado volvía a cambiar.
 *
 * Uso:
 *   php artisan stock:reconcile               -- muestra divergencias (dry-run)
 *   php artisan stock:reconcile --fix         -- corrige
 *   php artisan stock:reconcile --tenant=uuid -- solo un tenant
 */
class ReconcileStock extends Command
{
    protected $signature = 'stock:reconcile
                            {--fix        : Corregir las divergencias encontradas}
                            {--tenant=    : UUID del tenant a revisar (omitir = todos)}
                            {--threshold= : Solo reportar divergencias > N unidades (default: 0)}';

    protected $description = 'Detecta divergencias entre items.stock y la suma de stock en variantes/almacenes';

    private int $totalDivergences = 0;
    private int $totalFixed       = 0;

    public function __construct(private ItemVariantService $variantService)
    {
        parent::__construct();
    }

    public function handle(Environment $tenancy): int
    {
        $fix       = $this->option('fix');
        $tenantUuid = $this->option('tenant');
        $threshold = (float) ($this->option('threshold') ?? 0);

        $query = Website::query();
        if ($tenantUuid) {
            $query->where('uuid', $tenantUuid);
        }

        $query->chunk(20, function ($websites) use ($tenancy, $fix, $threshold) {
            foreach ($websites as $website) {
                try {
                    $tenancy->tenant($website);
                    $this->reconcileTenant($website->uuid, $fix, $threshold);
                } catch (\Throwable $e) {
                    $this->error("Error en tenant [{$website->uuid}]: {$e->getMessage()}");
                    Log::error('[stock:reconcile] Error en tenant', [
                        'tenant' => $website->uuid,
                        'error'  => $e->getMessage(),
                    ]);
                } finally {
                    $tenancy->tenant(null);
                }
            }
        });

        $this->newLine();
        $this->info("── Resumen ────────────────────────────────────");
        $this->line("Divergencias encontradas : {$this->totalDivergences}");
        if ($fix) {
            $this->line("Divergencias corregidas  : {$this->totalFixed}");
        } else {
            $this->comment("Ejecutar con --fix para corregir automáticamente.");
        }

        return 0;
    }

    private function reconcileTenant(string $uuid, bool $fix, float $threshold): void
    {
        // Solo productos con variantes activas
        $items = Item::where('has_variants', true)->get();

        if ($items->isEmpty()) return;

        $this->line("\nTenant: {$uuid} — {$items->count()} producto(s) con variantes");

        foreach ($items as $item) {
            $this->reconcileItem($item, $fix, $threshold);
        }
    }

    private function reconcileItem(Item $item, bool $fix, float $threshold): void
    {
        // Cálculo canónico: físico de las variantes ACTIVAS. Es lo mismo que
        // guarda propagateStock(), así que cualquier diferencia con lo
        // almacenado es una divergencia real y no un criterio distinto.
        $stock = $this->variantService->computeStock($item);

        $realStock   = (float) $stock['physical'];
        $storedStock = (float) ($item->getAttributes()['stock'] ?? 0); // campo crudo, no accessor

        // El nombre vive en items.description; items.name está a NULL casi siempre.
        $label = \Illuminate\Support\Str::limit($item->description ?: ('#' . $item->id), 40);

        $diff = abs($realStock - $storedStock);
        if ($diff <= $threshold) return;

        $this->totalDivergences++;

        $this->warn(sprintf(
            '  [DIVERGENCIA] Item #%d "%s" | guardado: %.4f | real: %.4f | diff: %.4f',
            $item->id,
            $label,
            $storedStock,
            $realStock,
            $diff
        ));

        if ($stock['committed'] > 0) {
            $this->line(sprintf(
                '                 (de los cuales %.4f están comprometidos por pedidos pendientes)',
                $stock['committed']
            ));
        }

        Log::warning('[stock:reconcile] Divergencia detectada', [
            'item_id'      => $item->id,
            'item_name'    => $item->description,
            'stock_stored' => $storedStock,
            'stock_real'   => $realStock,
            'diff'         => $diff,
        ]);

        if ($fix) {
            // No escribimos items.stock a mano: llamamos a la MISMA función que
            // usa la aplicación, que además deja coherentes las filas por almacén
            // y pone a cero las que ya no reciben stock de ninguna variante activa.
            $this->variantService->propagateStock($item);

            $this->totalFixed++;
            $this->info(sprintf('    ✓ Corregido → stock = %.4f', $realStock));

            Log::info('[stock:reconcile] Divergencia corregida', [
                'item_id'   => $item->id,
                'stock_old' => $storedStock,
                'stock_new' => $realStock,
            ]);
        }
    }
}
