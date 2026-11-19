<?php

namespace App\Services\Marketplace;

use App\Models\System\Configuration;
use App\Models\System\MarketplaceCategory;
use App\Models\System\MarketplaceListing;
use App\Models\System\PromoVideo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Lo que el inicio del marketplace muestra ANTES de la rejilla (fase 2 del
 * rediseño): la portada de temporada, «Compra por categoría» y los carruseles
 * de productos por categoría.
 *
 * Todo sale de UN calculo cacheado 10 minutos (`roots()`): dos consultas
 * ligeras (los listings publicados con categoria oficial, solo las columnas
 * que hacen falta, y el arbol de categorias) y el resto en PHP. Asi el inicio
 * no suma consultas pesadas por peticion: con el cache caliente, la vitrina
 * cuesta una lectura de cache y una consulta por id para pintar las tarjetas.
 */
class HomeShowcase
{
    public const CACHE_TTL = 600;
    public const CACHE_KEY = 'mp_home_showcase_v1';

    /** Productos por carrusel. */
    public const REEL_SIZE = 12;
    /**
     * Tarjetas que van en el HTML inicial de cada carrusel; el resto, en
     * «Ver todo». Cinco filas de 12 eran 60 tarjetas y >3.000 nodos: el DOM
     * y el TBT bajaron el inicio de 98 a 46 en móvil (2026-11-19).
     */
    public const REEL_RENDER = 6;
    /** Por debajo de esto no se pinta el carrusel de esa categoria. */
    public const REEL_MIN  = 4;

    /** Portada por defecto: la que deja la migracion. */
    public const HERO_DEFAULTS = [
        'enabled'     => true,
        'title'       => 'Navidad en casa',
        'subtitle'    => 'Adornos, luces y regalos de tiendas peruanas verificadas.',
        'cta_text'    => 'Ver Navidad',
        'cta_type'    => 'category',   // category | url
        'cta_url'     => null,
        'category'    => 'navidad',    // full_slug de la categoria oficial
        'listing_ids' => [],           // fotos elegidas a mano (opcional)
        'starts_at'   => null,         // Y-m-d, opcional
        'ends_at'     => null,         // Y-m-d, opcional
    ];

    /**
     * Raices VISIBLES del arbol oficial con productos publicados, ordenadas
     * por numero de productos (descendencia incluida). Cada una con la foto
     * de un producto real y los ids de su carrusel, ya mezclando tiendas.
     *
     * @return Collection<int, object{id:int,name:string,full_slug:string,icon:?string,count:int,image:?string,image_alt:?string,reel_ids:int[]}>
     */
    public function roots(): Collection
    {
        $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => $this->computeRoots());

        return collect($rows)->map(fn ($r) => (object) $r);
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY . '_rootmap');
        self::forgetVideos();
    }

    // ── «Míralo en video» ────────────────────────────────────────────────

    public const VIDEO_CACHE_KEY = 'mp_home_videos_v1';
    /** Videos en la fila del inicio. */
    public const VIDEO_ROW_SIZE = 12;

    /** Al aprobar, destacar, quitar o cambiar un video. */
    public static function forgetVideos(): void
    {
        Cache::forget(self::VIDEO_CACHE_KEY);
    }

    /**
     * La fila «Míralo en video»: videos publicados cuyo producto sigue
     * publicado en el marketplace. Primero los ★ destacados vigentes, luego
     * los aprobados más recientes. Vacía si no hay ninguno (la fila no sale).
     *
     * Cacheado 10 min como el resto de la vitrina: lo cacheado son solo los
     * pares (video, listing); por petición se cargan por id para respetar un
     * producto que se quedó sin stock o un extra que venció en esos minutos.
     *
     * @return Collection<int, object{video: PromoVideo, listing: MarketplaceListing}>
     */
    public function videoRow(): Collection
    {
        $pairs = Cache::remember(self::VIDEO_CACHE_KEY, self::CACHE_TTL, fn () => $this->computeVideoPairs());
        if (!$pairs) {
            return collect();
        }

        $videos = PromoVideo::publico()->whereIn('id', array_column($pairs, 'video'))->get()->keyBy('id');
        $listings = MarketplaceListing::published()->whereIn('id', array_column($pairs, 'listing'))->get()->keyBy('id');

        return collect($pairs)
            ->map(fn ($p) => isset($videos[$p['video']], $listings[$p['listing']])
                ? (object) ['video' => $videos[$p['video']], 'listing' => $listings[$p['listing']]]
                : null)
            ->filter(fn ($row) => $row && $row->video->embedSrc())
            ->values();
    }

    private function computeVideoPairs(): array
    {
        $today = now()->toDateString();
        $videos = PromoVideo::publico()
            ->orderByRaw('(is_featured = 1 AND (featured_until IS NULL OR featured_until >= ?)) DESC', [$today])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::VIDEO_ROW_SIZE * 3)
            ->get(['id', 'client_id', 'remote_item_id']);
        if ($videos->isEmpty()) {
            return [];
        }

        $listings = MarketplaceListing::published()
            ->whereIn('client_id', $videos->pluck('client_id')->unique()->all())
            ->whereIn('remote_item_id', $videos->pluck('remote_item_id')->unique()->all())
            ->get(['id', 'client_id', 'remote_item_id'])
            ->keyBy(fn ($l) => $l->client_id . '-' . $l->remote_item_id);

        $out = [];
        foreach ($videos as $v) {
            $l = $listings->get($v->client_id . '-' . $v->remote_item_id);
            if ($l) {
                $out[] = ['video' => (int) $v->id, 'listing' => (int) $l->id];
            }
            if (count($out) >= self::VIDEO_ROW_SIZE) {
                break;
            }
        }
        return $out;
    }

    /**
     * id de categoria => id de su raiz, para todo el arbol. depth_path es «/»
     * en una raiz y «/raiz/…/» debajo. Cacheado igual que la vitrina.
     *
     * @return array<int,int>
     */
    public function rootOfMap(): array
    {
        return Cache::remember(self::CACHE_KEY . '_rootmap', self::CACHE_TTL, function () {
            $rootOf = [];
            MarketplaceCategory::query()->get(['id', 'parent_id', 'depth_path'])
                ->each(function ($c) use (&$rootOf) {
                    if ($c->parent_id === null) {
                        $rootOf[$c->id] = (int) $c->id;
                        return;
                    }
                    $first = (int) (explode('/', trim((string) $c->depth_path, '/'))[0] ?? 0);
                    $rootOf[$c->id] = $first ?: (int) $c->id;
                });
            return $rootOf;
        });
    }

    private function computeRoots(): array
    {
        $roots = MarketplaceCategory::query()->active()->visible()->roots()
            ->get(['id', 'name', 'full_slug', 'icon'])
            ->keyBy('id');
        if ($roots->isEmpty()) {
            return [];
        }

        $rootOf = $this->rootOfMap();

        $now = now();
        $listings = MarketplaceListing::published()
            ->whereNotNull('marketplace_category_id')
            ->get([
                'id', 'hostname_id', 'title', 'marketplace_category_id', 'image_url', 'thumb_image_url',
                'is_on_offer', 'is_featured', 'featured_until', 'sort_score', 'view_count',
            ]);

        $byRoot = [];
        foreach ($listings as $l) {
            $root = $rootOf[$l->marketplace_category_id] ?? null;
            if ($root === null || !$roots->has($root)) {
                continue;
            }
            $featured = $l->is_featured && (!$l->featured_until || Carbon::parse($l->featured_until)->gt($now));
            $byRoot[$root][] = [
                'id'       => (int) $l->id,
                'hostname' => (int) $l->hostname_id,
                'title'    => (string) $l->title,
                'image'    => $this->realImage($l->thumb_image_url) ?: $this->realImage($l->image_url),
                // Ofertas y destacados delante, luego lo mas visto.
                'score'    => [(int) (bool) $l->is_on_offer, (int) $featured, (int) $l->view_count, (float) $l->sort_score, (int) $l->id],
            ];
        }

        $out = [];
        foreach ($byRoot as $rootId => $items) {
            usort($items, fn ($a, $b) => $b['score'] <=> $a['score']);
            $root  = $roots[$rootId];
            $photo = collect($items)->first(fn ($i) => $i['image']);

            $out[] = [
                'id'        => (int) $root->id,
                'name'      => (string) $root->name,
                'full_slug' => (string) $root->full_slug,
                'icon'      => $root->icon,
                'count'     => count($items),
                'image'     => $photo['image'] ?? null,
                // «Compra por categoría» pinta la foto a ~110 px en móvil.
                'image_small' => ImageVariantUrl::sibling($photo['image'] ?? null, ['_small']),
                'image_alt' => $photo['title'] ?? null,
                'reel_ids'  => $this->diversify(array_filter($items, fn ($i) => $i['image']), self::REEL_SIZE),
            ];
        }

        usort($out, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);
        return $out;
    }

    /**
     * Ronda por tiendas: el mejor de cada tienda, luego el segundo de cada
     * una… Es la misma diversidad que el listado (ver
     * project_marketplace_seller_diversity): un carrusel no debe parecer el
     * escaparate de una sola tienda. $items ya viene ordenado por puntuacion.
     */
    private function diversify(array $items, int $limit): array
    {
        $porTienda = [];
        foreach ($items as $i) {
            $porTienda[$i['hostname']][] = $i['id'];
        }
        $out = [];
        while (count($out) < $limit && $porTienda) {
            foreach ($porTienda as $h => &$ids) {
                $out[] = array_shift($ids);
                if (!$ids) unset($porTienda[$h]);
                if (count($out) >= $limit) break;
            }
            unset($ids);
        }
        return $out;
    }

    /** La foto «imagen no disponible» del ERP cuenta como imagen para published(), no para una vitrina. */
    private function realImage(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || stripos($url, 'imagen-no-disponible') !== false) {
            return null;
        }
        return $url;
    }

    // ── Portada de temporada ─────────────────────────────────────────────

    /** Config guardada mezclada con los valores por defecto. */
    public static function heroConfig(?Configuration $cfg = null): array
    {
        $cfg = $cfg ?? Configuration::firstCached();
        $raw = $cfg->marketplace_hero ?? null;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }
        return array_merge(self::HERO_DEFAULTS, is_array($raw) ? $raw : []);
    }

    /**
     * La portada lista para pintar, o null si esta apagada, fuera de fechas o
     * a medio configurar (sin titulo o sin destino). Null = no ocupa espacio.
     *
     * @return object{title:string,subtitle:?string,cta_text:string,cta_url:string,photos:Collection}|null
     */
    public function hero(): ?object
    {
        $h = self::heroConfig();
        if (empty($h['enabled']) || trim((string) $h['title']) === '') {
            return null;
        }
        $today = now()->toDateString();
        if (!empty($h['starts_at']) && $today < $h['starts_at']) return null;
        if (!empty($h['ends_at'])   && $today > $h['ends_at'])   return null;

        $category = null;
        if (!empty($h['category'])) {
            $category = Cache::remember('mp_hero_cat_v1_' . md5($h['category']), self::CACHE_TTL, function () use ($h) {
                return MarketplaceCategory::query()->active()
                    ->where('full_slug', $h['category'])
                    ->first(['id', 'name', 'full_slug', 'is_visible_in_marketplace']) ?: false;
            }) ?: null;
        }

        $url = null;
        if (($h['cta_type'] ?? 'category') === 'url' && !empty($h['cta_url'])) {
            $url = $h['cta_url'];
        } elseif ($category) {
            // Categoria publicada → su pagina. Si sigue oculta (Navidad lo
            // estuvo: su pagina da 404 a proposito), el boton lleva al listado
            // filtrado por esa categoria, que si funciona, en vez de a un 404.
            $url = $category->is_visible_in_marketplace
                ? route('marketplace.category_official', ['fullSlug' => $category->full_slug])
                : route('marketplace.index', ['official_category_id' => $category->id]);
        }
        if (!$url) {
            return null;
        }

        return (object) [
            'title'    => (string) $h['title'],
            'subtitle' => $h['subtitle'] ?: null,
            'cta_text' => $h['cta_text'] ?: 'Ver más',
            'cta_url'  => $url,
            'photos'   => $this->heroPhotos($h, $category),
        ];
    }

    /** 1-2 productos publicados con foto: los elegidos a mano o los mejores de la categoria. */
    private function heroPhotos(array $h, $category): Collection
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($h['listing_ids'] ?? []))));
        $key = 'mp_hero_photos_v2_' . md5(json_encode([$ids, $category->id ?? null]));

        $chosen = Cache::remember($key, self::CACHE_TTL, function () use ($ids, $category) {
            $q = MarketplaceListing::published()->whereNotNull('image_url')
                ->where('image_url', 'not like', '%imagen-no-disponible%');
            if ($ids) {
                $q->whereIn('id', $ids)->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')');
            } elseif ($category) {
                $q->inOfficialCategory($category->id)
                  ->orderByDesc('is_on_offer')->orderByDesc('view_count')->orderByDesc('sort_score');
            } else {
                return [];
            }
            return $q->limit(2)->get(['id', 'slug', 'title', 'image_url', 'thumb_image_url', 'hostname_id'])
                ->map(function ($l) {
                    $image = $this->realImage($l->thumb_image_url) ?: $l->image_url;
                    return [
                        'slug'  => $l->slug,
                        'title' => $l->title,
                        'image' => $image,
                        // La portada pinta la foto a 84-150 px: con la de 256
                        // basta (la de 512 pesaba 50 KB para eso).
                        'image_small' => ImageVariantUrl::sibling($image, ['_small']),
                    ];
                })->all();
        });

        return collect($chosen)->map(fn ($p) => (object) $p);
    }
}
