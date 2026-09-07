<?php

namespace App\Console\Commands;

use App\Models\System\MarketplaceCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Publica u oculta una rama entera del arbol oficial del marketplace, y
 * opcionalmente la manda al frente del menu.
 *
 * Es el interruptor de campana: las ramas de temporada (Navidad, Halloween,
 * Dia de la Madre) se crean ocultas, se llenan de productos con
 * `items:assign-category`, y recien entonces se publican con este comando.
 * Al terminar la campana se ocultan con --hide sin desasignar un solo item.
 *
 * Por que toda la rama y no solo la raiz: el menu publico filtra las raices
 * por is_visible_in_marketplace, pero `categoryOfficial` tambien exige
 * visible() para renderizar cada hija. Publicar solo la raiz deja las
 * subcategorias en 404.
 *
 * --first reordena las raices para que esta quede primera. sort_order es
 * unsigned, asi que no alcanza con ponerle -1: hay que correr las demas +1.
 *
 * Por defecto simula; solo escribe con --apply.
 *
 * Uso:
 *   php artisan marketplace:publish-category --category=227 --first
 *   php artisan marketplace:publish-category --category=227 --first --apply
 *   php artisan marketplace:publish-category --category=227 --hide --apply
 */
class PublishMarketplaceCategory extends Command
{
    protected $signature = 'marketplace:publish-category
                            {--category= : ID de la categoria raiz de la rama (obligatorio)}
                            {--first : Reordena las raices para dejar esta primera}
                            {--hide : Oculta la rama en vez de publicarla}
                            {--apply : Escribe de verdad. Sin este flag solo simula}';

    protected $description = 'Publica u oculta una rama completa del arbol del marketplace';

    public function handle(): int
    {
        $categoryId = (int) $this->option('category');
        $ocultar    = (bool) $this->option('hide');
        $primero    = (bool) $this->option('first');
        $apply      = (bool) $this->option('apply');

        if ($categoryId <= 0) {
            $this->error('Falta --category.');
            return self::FAILURE;
        }

        $raiz = MarketplaceCategory::find($categoryId);
        if (!$raiz) {
            $this->error("La categoria #{$categoryId} no existe.");
            return self::FAILURE;
        }

        $ids     = $raiz->descendantAndSelfIds();
        $visible = $ocultar ? 0 : 1;

        $this->info(($apply ? 'APLICANDO' : 'SIMULACION (sin --apply no escribe)')
            . ' | ' . ($ocultar ? 'OCULTANDO' : 'PUBLICANDO')
            . " rama #{$raiz->id} {$raiz->full_slug} (" . count($ids) . ' nodos)');

        $nodos = MarketplaceCategory::whereIn('id', $ids)
            ->orderBy('level')->orderBy('sort_order')
            ->get(['id', 'level', 'name', 'full_slug', 'is_visible_in_marketplace']);

        $filas = [];
        foreach ($nodos as $n) {
            $filas[] = [
                $n->id,
                'L' . $n->level,
                mb_substr($n->name, 0, 38),
                $n->is_visible_in_marketplace ? 'visible' : 'oculta',
                $visible ? 'visible' : 'oculta',
            ];
        }
        $this->table(['ID', 'Nivel', 'Nombre', 'Antes', 'Despues'], $filas);

        if ($primero && $raiz->parent_id) {
            $this->warn('--first se ignora: la categoria no es una raiz.');
            $primero = false;
        }

        if ($primero) {
            $this->line('Ademas: se corre +1 el sort_order de las demas raices y esta queda en 0.');
        }

        if (!$apply) {
            $this->warn('Simulacion. Repetir con --apply para escribir.');
            return self::SUCCESS;
        }

        DB::connection('system')->transaction(function () use ($ids, $visible, $primero, $raiz) {
            MarketplaceCategory::whereIn('id', $ids)
                ->update(['is_visible_in_marketplace' => $visible]);

            if ($primero) {
                MarketplaceCategory::whereNull('parent_id')
                    ->where('id', '!=', $raiz->id)
                    ->increment('sort_order');
                MarketplaceCategory::where('id', $raiz->id)->update(['sort_order' => 0]);
            }
        });

        // Sin esto el cambio no se ve hasta 30 min despues y parece que fallo.
        foreach (['marketplace_public_roots_v3', 'marketplace_categories_tree_v2', 'marketplace_categories_flat_v2'] as $key) {
            Cache::forget($key);
        }

        $this->info('Listo. Rama ' . ($visible ? 'publicada' : 'oculta') . ' y caches del arbol limpiadas.');
        $this->line('Los chips por tenant (ec_*_mp_cats_with_items_v2) expiran solos en 30 min.');

        return self::SUCCESS;
    }
}
