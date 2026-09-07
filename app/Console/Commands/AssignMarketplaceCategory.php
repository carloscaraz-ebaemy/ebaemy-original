<?php

namespace App\Console\Commands;

use App\Models\System\MarketplaceCategory;
use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Asigna la categoria oficial del marketplace a un lote explicito de items
 * de un tenant.
 *
 * Es la contraparte de escritura de `items:scan-seasonal`: aquel encuentra
 * los candidatos, este aplica la decision ya tomada. Los IDs van explicitos
 * a proposito — nunca se asigna "todo lo que matchee una palabra", porque
 * los diccionarios de temporada tienen falsos positivos conocidos
 * (NEOPRENO contiene "reno", "corona" puede ser corona dental).
 *
 * Escribe SOLO `items.marketplace_category_id`. No toca precios, stock,
 * publicacion ni la categoria interna del tenant.
 *
 * Por defecto hace dry-run: hay que pasar --apply para que escriba.
 *
 * Uso:
 *   php artisan items:assign-category --tenant=ebaemy_alasitas --items=117,118 --category=230
 *   php artisan items:assign-category --tenant=ebaemy_alasitas --items=117,118 --category=230 --apply
 */
class AssignMarketplaceCategory extends Command
{
    protected $signature = 'items:assign-category
                            {--tenant= : UUID del website (obligatorio)}
                            {--items= : IDs de items separados por coma (obligatorio)}
                            {--category= : ID de la marketplace_category destino (obligatorio)}
                            {--apply : Escribe de verdad. Sin este flag solo simula}';

    protected $description = 'Asigna marketplace_category_id a un lote explicito de items de un tenant';

    public function handle(): int
    {
        $uuid       = (string) $this->option('tenant');
        $itemsRaw   = (string) $this->option('items');
        $categoryId = (int) $this->option('category');
        $apply      = (bool) $this->option('apply');

        if ($uuid === '' || $itemsRaw === '' || $categoryId <= 0) {
            $this->error('Faltan opciones: --tenant, --items y --category son obligatorios.');
            return self::FAILURE;
        }

        $ids = collect(explode(',', $itemsRaw))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            $this->error('La lista --items no tiene IDs validos.');
            return self::FAILURE;
        }

        // La categoria vive en la BD system: validarla ANTES de tocar al tenant.
        // Asignar un id inexistente deja el item apuntando a la nada y no
        // rompe nada visiblemente — simplemente no aparece en el marketplace.
        $categoria = MarketplaceCategory::find($categoryId);
        if (!$categoria) {
            $this->error("La marketplace_category #{$categoryId} no existe.");
            return self::FAILURE;
        }
        if (!$categoria->is_active) {
            $this->error("La categoria #{$categoryId} ({$categoria->name}) esta inactiva.");
            return self::FAILURE;
        }

        $website = Website::where('uuid', $uuid)->first();
        if (!$website) {
            $this->error("No existe el tenant '{$uuid}'.");
            return self::FAILURE;
        }

        app(Environment::class)->tenant($website);

        if (!Schema::connection('tenant')->hasColumn('items', 'marketplace_category_id')) {
            $this->error("El tenant '{$uuid}' no tiene la columna marketplace_category_id (falta migrar).");
            return self::FAILURE;
        }

        $rows = DB::connection('tenant')->table('items')
            ->whereIn('id', $ids)
            ->get(['id', 'description', 'marketplace_category_id']);

        $faltantes = $ids->diff($rows->pluck('id'));
        if ($faltantes->isNotEmpty()) {
            $this->warn('IDs que no existen en este tenant y se ignoran: ' . $faltantes->implode(', '));
        }

        if ($rows->isEmpty()) {
            $this->error('Ninguno de los IDs existe en este tenant.');
            return self::FAILURE;
        }

        $this->info(($apply ? 'APLICANDO' : 'SIMULACION (sin --apply no escribe)')
            . " | {$uuid} -> #{$categoria->id} {$categoria->full_slug}");

        $filas = [];
        $porCambiar = [];
        foreach ($rows as $r) {
            $antes = $r->marketplace_category_id ?: '-';
            $igual = (int) $r->marketplace_category_id === $categoryId;
            if (!$igual) {
                $porCambiar[] = $r->id;
            }
            $filas[] = [
                $r->id,
                mb_substr((string) $r->description, 0, 46),
                $antes,
                $igual ? '(ya estaba)' : $categoryId,
            ];
        }
        $this->table(['ID', 'Producto', 'Antes', 'Despues'], $filas);

        if (empty($porCambiar)) {
            $this->info('No hay nada que cambiar: todos ya tenian esa categoria.');
            return self::SUCCESS;
        }

        if (!$apply) {
            $this->warn('Simulacion. ' . count($porCambiar) . ' items cambiarian. Repetir con --apply para escribir.');
            return self::SUCCESS;
        }

        $afectados = DB::connection('tenant')->table('items')
            ->whereIn('id', $porCambiar)
            ->update(['marketplace_category_id' => $categoryId]);

        $this->info("Listo: {$afectados} items actualizados.");

        return self::SUCCESS;
    }
}
