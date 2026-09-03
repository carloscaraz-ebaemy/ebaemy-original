<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Barrido READ-ONLY de productos de campaña de temporada en los catálogos
 * de todos los tenants.
 *
 * Nació para responder "qué productos deberían estar en la rama Navidad"
 * (auditoría 2026-09-02) — pero sirve igual para Halloween, Día de la Madre
 * o cualquier campaña futura: la temporada es solo un diccionario de palabras.
 *
 * Cada temporada define dos grupos:
 *   - `seguras`: si el nombre las contiene, es producto de campaña sin duda
 *     ("navideño", "nochebuena", "pesebre").
 *   - `ambiguas`: sugieren campaña pero dan falsos positivos y necesitan ojo
 *     humano ("corona" -> corona dental, "pino" -> madera pino, "luces" ->
 *     luces de auto). Se listan aparte, nunca mezcladas con las seguras.
 *
 * NO escribe nada. La reasignación se hace después desde
 * /admin/marketplace/categories/bulk-assign.
 *
 * Uso:
 *   php artisan items:scan-seasonal                      # Navidad, todos los tenants
 *   php artisan items:scan-seasonal --season=halloween
 *   php artisan items:scan-seasonal --tenant=ebaemy_alasitas
 *   php artisan items:scan-seasonal --only-sure          # oculta los ambiguos
 *   php artisan items:scan-seasonal --unassigned         # solo los que aun no
 *                                                        # tienen categoria oficial
 */
class ScanSeasonalItems extends Command
{
    protected $signature = 'items:scan-seasonal
                            {--season=navidad : Temporada a buscar (navidad, halloween, madre, san-valentin)}
                            {--tenant= : UUID de un website especifico}
                            {--only-sure : Solo coincidencias seguras, sin las ambiguas}
                            {--unassigned : Solo items sin marketplace_category_id}
                            {--limit=300 : Maximo de items por tenant}';

    protected $description = 'Lista productos de temporada (Navidad, etc.) por tenant, con su categoria actual. Solo lectura.';

    /**
     * Diccionarios por temporada. Van sobre `items.description` (el nombre
     * comercial real) y `items.second_name`, en minusculas.
     * Sin acentos en las claves: la colacion de MySQL es accent-insensitive,
     * asi que "arbol" tambien matchea "árbol".
     */
    private const SEASONS = [
        'navidad' => [
            'seguras'  => ['navid', 'christm', 'xmas', 'noel', 'nochebuena', 'santa claus',
                           'muerdago', 'villancico', 'trineo', 'pesebre', 'nacimiento'],
            'ambiguas' => ['arbol', 'pino', 'adorno', 'esfera', 'guirnalda', 'corona',
                           'escarcha', 'campana', 'luces', 'duende', 'elfo', 'reno', 'rodolfo'],
        ],
        'halloween' => [
            'seguras'  => ['halloween', 'calabaza', 'disfraz', 'terror', 'esqueleto'],
            'ambiguas' => ['bruja', 'fantasma', 'arana', 'murcielago', 'calavera', 'zombie'],
        ],
        'madre' => [
            'seguras'  => ['dia de la madre', 'dia de mama'],
            'ambiguas' => ['ramo', 'flores', 'chocolate', 'peluche', 'tarjeta'],
        ],
        'san-valentin' => [
            'seguras'  => ['san valentin', 'enamorados', 'valentine'],
            'ambiguas' => ['corazon', 'rosa', 'peluche', 'chocolate', 'ramo'],
        ],
    ];

    public function handle(): int
    {
        $season = (string) $this->option('season');

        if (!isset(self::SEASONS[$season])) {
            $this->error("Temporada '{$season}' desconocida. Disponibles: " . implode(', ', array_keys(self::SEASONS)));
            return self::FAILURE;
        }

        $seguras  = self::SEASONS[$season]['seguras'];
        $ambiguas = $this->option('only-sure') ? [] : self::SEASONS[$season]['ambiguas'];
        $todas    = array_merge($seguras, $ambiguas);

        $uuid    = $this->option('tenant');
        $soloSin = (bool) $this->option('unassigned');
        $limit   = max(1, (int) $this->option('limit'));

        $websites = $uuid ? Website::where('uuid', $uuid)->get() : Website::all();

        if ($websites->isEmpty()) {
            $this->error('No se encontraron tenants.');
            return self::FAILURE;
        }

        $this->info("Temporada: {$season} - " . count($seguras) . ' palabras seguras, '
            . count($ambiguas) . ' ambiguas | ' . $websites->count() . ' tenants');

        $totalSeguros   = 0;
        $totalAmbiguos  = 0;
        $totalSinMp     = 0;
        $tenantsConHits = 0;
        $fallidos       = [];

        foreach ($websites as $w) {
            try {
                app(Environment::class)->tenant($w);

                if (!Schema::connection('tenant')->hasTable('items')) {
                    continue;
                }
                // La columna llego con la Fase 2 del marketplace; un tenant sin
                // migrar todavia no la tiene y la query explotaria.
                $tieneMp = Schema::connection('tenant')->hasColumn('items', 'marketplace_category_id');
                // `items` del ERP legacy no tiene soft-deletes; algunos schemas
                // migrados si. Filtrar a ciegas revienta con 1054.
                $tieneSoftDelete = Schema::connection('tenant')->hasColumn('items', 'deleted_at');

                $query = DB::connection('tenant')->table('items as i')
                    ->leftJoin('categories as c', 'c.id', '=', 'i.category_id')
                    ->where(function ($sub) use ($todas) {
                        foreach ($todas as $palabra) {
                            // OJO: el nombre comercial del producto vive en
                            // `description`, no en `name` — en el ERP legacy
                            // `items.name` esta NULL en todos los registros.
                            // Buscar por `name` devuelve cero coincidencias
                            // siempre, y parece un catalogo limpio cuando no
                            // lo es. `second_name` es el alias opcional.
                            $sub->orWhere('i.description', 'like', '%' . $palabra . '%')
                                ->orWhere('i.second_name', 'like', '%' . $palabra . '%');
                        }
                    });

                if ($tieneSoftDelete) {
                    $query->whereNull('i.deleted_at');
                }

                if ($soloSin && $tieneMp) {
                    $query->whereNull('i.marketplace_category_id');
                }

                $columnas = ['i.id', 'i.description as nombre', 'i.internal_id', 'i.apply_store', 'c.name as cat'];
                if ($tieneMp) {
                    $columnas[] = 'i.marketplace_category_id as mp';
                }

                $rows = $query->orderBy('i.description')->limit($limit)->get($columnas);

                if ($rows->isEmpty()) {
                    continue;
                }
                $tenantsConHits++;

                $filas = [];
                foreach ($rows as $r) {
                    $nombre   = mb_strtolower((string) $r->nombre);
                    $esSeguro = false;
                    foreach ($seguras as $palabra) {
                        if (mb_strpos($nombre, $palabra) !== false) {
                            $esSeguro = true;
                            break;
                        }
                    }
                    $esSeguro ? $totalSeguros++ : $totalAmbiguos++;

                    $mp = $tieneMp ? ($r->mp ?: '-') : 'n/a';
                    if ($mp === '-') {
                        $totalSinMp++;
                    }

                    $filas[] = [
                        $esSeguro ? 'SI' : '?',
                        $r->id,
                        mb_substr((string) $r->nombre, 0, 46),
                        $r->internal_id,
                        mb_substr($r->cat ?: 'SIN CATEGORIA', 0, 22),
                        $mp,
                        $r->apply_store ? 'si' : 'no',
                    ];
                }

                $this->line('');
                $this->info("-> {$w->uuid} ({$rows->count()})");
                $this->table(['?', 'ID', 'Producto', 'Codigo', 'Categoria interna', 'Cat. oficial', 'En tienda'], $filas);
            } catch (\Throwable $e) {
                $fallidos[] = $w->uuid . ': ' . mb_substr($e->getMessage(), 0, 90);
            }
        }

        $this->line('');
        $this->info('=== RESUMEN ===');
        $this->line("Tenants revisados     : {$websites->count()} (con coincidencias: {$tenantsConHits})");
        $this->line("Coincidencias seguras : {$totalSeguros}");
        $this->line("Ambiguas (revisar)    : {$totalAmbiguos}");
        $this->line("Sin categoria oficial : {$totalSinMp}");

        if ($fallidos) {
            $this->line('');
            $this->warn('Tenants con error:');
            foreach ($fallidos as $f) {
                $this->line('  ' . $f);
            }
        }

        return self::SUCCESS;
    }
}
