<?php

namespace App\Console\Commands;

use App\Models\System\MarketplaceCategory;
use App\Models\System\MarketplaceListing;
use App\Services\System\MarketplaceListingSyncService;
use App\Services\System\SearchSynonyms;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Qué le falta al catálogo publicado para que se pueda encontrar, tienda por
 * tienda, y qué categoría le pegaría a cada producto que no la tiene.
 *
 * Ya existía quién APLICA la categoría (`items:assign-category`, con dry-run
 * y IDs explícitos a propósito). Lo que faltaba era quién dice CUÁL poner: sin
 * eso hay que abrir los productos de uno en uno, y por eso quedan 57 sin
 * categoría.
 *
 * Este comando **no escribe nada**. Diagnostica y deja los comandos de
 * `items:assign-category` listos para revisar y pegar — la decisión sigue
 * siendo de una persona, porque las propuestas por texto tienen falsos
 * positivos conocidos («NEOPRENO» contiene «reno»).
 *
 * Por qué importa cada hueco:
 *  - sin categoría oficial, el producto no aparece al navegar por categorías
 *    NI en el feed `?categoria=` que se usa para anunciar por categoría;
 *  - sin marca, no lo encuentra quien busca por marca, que es como busca
 *    mucha gente, y la faceta de marcas del buscador se queda corta.
 *
 *   php artisan marketplace:catalog-gaps
 *   php artisan marketplace:catalog-gaps --tienda=alasitas
 *   php artisan marketplace:catalog-gaps --sugerencias=30
 */
class MarketplaceCatalogGaps extends Command
{
    protected $signature = 'marketplace:catalog-gaps
                            {--tienda= : Acota a un subdominio de tienda}
                            {--sugerencias=20 : Cuántas propuestas de categoría mostrar}
                            {--json : Salida en JSON para encadenar con otra cosa}';

    protected $description = 'Diagnostica qué productos publicados no se pueden encontrar (sin categoría o sin marca) y propone categoría';

    /** Palabras que no distinguen nada y ensucian la propuesta. */
    private const VACIAS = [
        'para', 'con', 'sin', 'por', 'del', 'las', 'los', 'una', 'uno', 'unos', 'unas',
        'cm', 'mm', 'ml', 'kg', 'und', 'pack', 'set', 'tipo', 'modelo', 'color', 'talla',
        'nuevo', 'nueva', 'grande', 'pequeno', 'mediano',
    ];

    public function handle(): int
    {
        $tienda = trim((string) $this->option('tienda'));

        $base = MarketplaceListing::published();

        if ($tienda !== '') {
            $base->where('tenant_fqdn', 'like', strtolower($tienda) . '.%');
        }

        $listings = (clone $base)->get([
            'id', 'title', 'tenant_fqdn', 'tenant_name', 'slug',
            'marketplace_category_id', 'brand_name', 'category_name', 'remote_item_id', 'hostname_id',
        ]);

        if ($listings->isEmpty()) {
            $this->warn('No hay productos publicados que coincidan.');

            return self::SUCCESS;
        }

        $sinCategoria = $listings->whereNull('marketplace_category_id');
        $sinMarca     = $listings->filter(fn ($l) => trim((string) $l->brand_name) === '');

        if ($this->option('json')) {
            $this->line(json_encode([
                'publicados'     => $listings->count(),
                'sin_categoria'  => $sinCategoria->count(),
                'sin_marca'      => $sinMarca->count(),
                'por_tienda'     => $this->porTienda($listings)->values()->all(),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->resumen($listings, $sinCategoria, $sinMarca);
        $this->tablaPorTienda($listings);
        $this->propuestas($sinCategoria);

        return self::SUCCESS;
    }

    // ══════════════════════════════════════════════════════════════

    private function resumen($listings, $sinCategoria, $sinMarca): void
    {
        $total = $listings->count();
        $pct   = fn ($n) => $total > 0 ? round($n / $total * 100) . ' %' : '0 %';

        $this->line('');
        $this->info('Catálogo publicado del marketplace');
        $this->line('');
        $this->table(['', 'Productos', 'Qué implica'], [
            ['Publicados', number_format($total), '—'],
            [
                'Sin categoría oficial',
                number_format($sinCategoria->count()) . '  (' . $pct($sinCategoria->count()) . ')',
                'no salen al navegar por categorías ni en el feed ?categoria= de los anuncios',
            ],
            [
                'Sin marca',
                number_format($sinMarca->count()) . '  (' . $pct($sinMarca->count()) . ')',
                'no los encuentra quien busca por marca',
            ],
        ]);
    }

    private function porTienda($listings)
    {
        return $listings->groupBy('tenant_fqdn')->map(function ($g, $fqdn) {
            $sub = strtolower(strtok((string) $fqdn, '.')) ?: $fqdn;

            return [
                'tienda'        => $sub,
                'nombre'        => (string) ($g->first()->tenant_name ?? ''),
                'publicados'    => $g->count(),
                'sin_categoria' => $g->whereNull('marketplace_category_id')->count(),
                'sin_marca'     => $g->filter(fn ($l) => trim((string) $l->brand_name) === '')->count(),
            ];
        })->sortByDesc('sin_categoria');
    }

    private function tablaPorTienda($listings): void
    {
        $filas = $this->porTienda($listings)->map(fn ($t) => [
            $t['tienda'],
            number_format($t['publicados']),
            $t['sin_categoria'] > 0 ? (string) $t['sin_categoria'] : '—',
            $t['sin_marca'] > 0 ? (string) $t['sin_marca'] : '—',
        ])->values()->all();

        $this->line('');
        $this->table(['Tienda', 'Publicados', 'Sin categoría', 'Sin marca'], $filas);
    }

    /**
     * Propone una categoría hoja para cada producto que no la tiene, casando
     * las palabras del título contra los nombres de las categorías.
     *
     * La propuesta se enseña con su puntuación para que se vea de un vistazo
     * cuáles son fiables y cuáles hay que mirar: nunca se aplica sola.
     */
    private function propuestas($sinCategoria): void
    {
        if ($sinCategoria->isEmpty()) {
            $this->line('');
            $this->info('Todos los productos publicados tienen categoría oficial.');

            return;
        }

        $categorias = MarketplaceCategory::query()
            ->active()->visible()->leaves()->publishable()
            ->get(['id', 'name', 'full_slug'])
            ->map(fn ($c) => [
                'id'       => (int) $c->id,
                'name'     => (string) $c->name,
                'slug'     => (string) $c->full_slug,
                'tokens'   => $this->tokens($c->name),
            ])
            ->filter(fn ($c) => !empty($c['tokens']))
            ->values();

        if ($categorias->isEmpty()) {
            $this->warn('No hay categorías hoja publicables contra las que proponer.');

            return;
        }

        $limite = max(1, (int) $this->option('sugerencias'));

        $propuestas = $sinCategoria
            ->map(function ($l) use ($categorias) {
                $match = $this->mejorCategoria($l->title, $l->category_name, $categorias);

                return $match ? array_merge($match, ['listing' => $l]) : null;
            })
            ->filter()
            ->sortByDesc('score')
            ->take($limite);

        $this->line('');

        if ($propuestas->isEmpty()) {
            $this->warn('Ningún título se parece lo bastante a una categoría. Hay que asignarlas a mano.');

            return;
        }

        $this->info('Categoría propuesta (revisar antes de aplicar)');
        $this->line('');
        $this->table(
            ['Producto', 'Tienda', 'Categoría propuesta', 'Cubre', 'Coincide en', 'Según'],
            $propuestas->map(fn ($p) => [
                mb_substr((string) $p['listing']->title, 0, 34),
                strtolower(strtok((string) $p['listing']->tenant_fqdn, '.')),
                mb_substr($p['name'], 0, 24),
                $p['aciertos'] . '/' . $p['de'],
                implode(', ', $p['por']),
                $p['fuente'],
            ])->values()->all()
        );

        $this->line('');
        $this->line('  «Cubre» = cuantas palabras de la categoria oficial aparecen en el producto.');
        $this->line('  Las que dicen «segun: categoria de la tienda» son las fiables: el vendedor');
        $this->line('  ya lo habia clasificado y aqui solo se traduce al arbol del marketplace.');
        $this->line('  Las que vienen del titulo hay que mirarlas — «Coincide en» dice por que.');

        $this->comandos($propuestas);
    }

    /**
     * Agrupa las propuestas por (tienda, categoría) y escribe el comando de
     * `items:assign-category` correspondiente. Sin `--apply`: el que lo pegue
     * verá primero la simulación.
     */
    private function comandos($propuestas): void
    {
        $uuids = DB::connection('system')->table('hostnames')
            ->join('websites', 'websites.id', '=', 'hostnames.website_id')
            ->pluck('websites.uuid', 'hostnames.id');

        $this->line('');
        $this->info('Para aplicarlas (revisá cada línea; nada se escribe sin --apply):');
        $this->line('');

        $propuestas
            ->groupBy(fn ($p) => $p['listing']->hostname_id . '|' . $p['id'])
            ->each(function ($grupo, $clave) use ($uuids) {
                [$hostnameId, $categoriaId] = explode('|', $clave);

                $uuid = $uuids[(int) $hostnameId] ?? null;
                $items = $grupo->pluck('listing.remote_item_id')->filter()->implode(',');

                if (!$uuid || $items === '') {
                    return;
                }

                $this->line('  # ' . $grupo->first()['name'] . ' — ' . $grupo->count() . ' producto(s)');
                $this->line("  php artisan items:assign-category --tenant={$uuid} --items={$items} --category={$categoriaId}");
                $this->line('');
            });
    }

    /**
     * La categoría que más palabras comparte con el título. Se apoya en el
     * diccionario de sinónimos del buscador, así que «asiento» también apunta
     * a las categorías de sillas.
     *
     * @return array{id:int,name:string,score:float}|null
     */
    /**
     * `protected` para que un test pueda ejercer la regla de propuesta con
     * categorias conocidas: es la logica delicada del comando y la que decide
     * si una sugerencia es fiable o un falso positivo.
     */
    protected function mejorCategoria(?string $titulo, ?string $categoriaTienda, $categorias): ?array
    {
        $deTitulo  = $this->expandir($this->tokens($titulo));
        // La categoria que la tienda ya le puso al producto en su propio
        // catalogo. Es una senal MUCHO mas limpia que el titulo —esta curada
        // por quien vende— y es lo que resuelve el caso real que destapo esta
        // auditoria: 44 productos "sin categoria" que en realidad ya estaban
        // clasificados como "Plantas Artificiales" o "Macetas" en su tienda,
        // y solo faltaba traducirlo al arbol oficial del marketplace.
        $deTienda  = $this->expandir($this->tokens($categoriaTienda));

        if (empty($deTitulo) && empty($deTienda)) {
            return null;
        }

        $mejor = null;

        foreach ($categorias as $cat) {
            $aciertosTitulo = 0;
            $aciertosTienda = 0;
            $coincidencias  = [];

            foreach ($cat['tokens'] as $ct) {
                $enTienda = isset($deTienda[$ct]);
                $enTitulo = isset($deTitulo[$ct]);

                if (!$enTienda && !$enTitulo) {
                    continue;
                }

                $coincidencias[] = $ct;

                if ($enTienda) {
                    $aciertosTienda++;
                } else {
                    $aciertosTitulo++;
                }
            }

            $aciertos = $aciertosTitulo + $aciertosTienda;

            if ($aciertos === 0) {
                continue;
            }

            // La coincidencia por categoria de la tienda pesa el doble.
            // Proporcion de la categoria cubierta: acertar «Zapatillas» entero
            // vale mas que acertar una palabra de «Ropa de cama y bano».
            $score = $aciertosTitulo + ($aciertosTienda * 2)
                   + ($aciertos / max(1, count($cat['tokens'])));

            if ($mejor === null || $score > $mejor['score']) {
                $mejor = [
                    'id'       => $cat['id'],
                    'name'     => $cat['name'],
                    'score'    => $score,
                    'aciertos' => $aciertos,
                    'de'       => count($cat['tokens']),
                    // Qué palabra provocó la propuesta. Es lo que convierte la
                    // revisión en un vistazo: con «Protector de mueble» →
                    // «Protector solar» por «protector», el falso positivo se
                    // ve solo. Afinar el algoritmo hasta distinguirlo seria
                    // otro proyecto; ensenar la evidencia cuesta una columna.
                    'por'      => $coincidencias,
                    'fuente'   => $aciertosTienda > 0 ? 'categoria de la tienda' : 'titulo',
                ];
            }
        }

        return $mejor;
    }

    /** Tokens mas sus sinonimos, como mapa para buscar en O(1). */
    private function expandir(array $tokens): array
    {
        $out = [];

        foreach ($tokens as $t) {
            foreach (SearchSynonyms::expand($t) as $v) {
                $out[$v] = true;
            }
        }

        return $out;
    }

    /** Palabras significativas de un texto, normalizadas. */
    private function tokens(?string $texto): array
    {
        $norm = MarketplaceListingSyncService::normalizeForSearch((string) $texto);
        $norm = preg_replace('/[^a-z0-9ñ ]+/u', ' ', $norm);

        return collect(explode(' ', $norm))
            ->filter(fn ($p) => mb_strlen($p) >= 4 && !in_array($p, self::VACIAS, true))
            ->unique()
            ->values()
            ->all();
    }
}
