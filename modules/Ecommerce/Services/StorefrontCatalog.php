<?php

namespace Modules\Ecommerce\Services;

use App\Models\Tenant\ItemVariant;
use App\Services\Tenant\StockQueryService;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

/**
 * StorefrontCatalog — reglas del escaparate del tenant que comparten el
 * listado, la búsqueda, el autocompletado y los carruseles.
 *
 * 1. PRODUCTO SIN IMAGEN NO SE MUESTRA (decisión del dueño 2026-11-18).
 *    «Sin imagen» = `items.image` vacío o el marcador
 *    'imagen-no-disponible.jpg' Y ninguna variante ACTIVA con
 *    `item_variants.image`. Es el mismo criterio que el marketplace
 *    (MarketplaceListing::scopeHasImage, que mira la foto del padre o la de
 *    una variante activa) y que el reporte del panel
 *    (ImageProcessingService::queryItemsWithoutImage, que fija qué cuenta
 *    como vacío). La ficha por URL sigue accesible y el vendedor lo sigue
 *    viendo en su panel: esto solo filtra los LISTADOS públicos.
 *
 * 2. BÚSQUEDA CON VARIANTES COMO TARJETAS, igual que el marketplace
 *    (MarketplaceController::explodeVariantsIntoCards). Solo con texto de
 *    búsqueda y después de paginar; ver explodeVariantsIntoCards().
 */
class StorefrontCatalog
{
    public const NO_IMAGE = 'imagen-no-disponible.jpg';

    /** Tope de tarjetas de variante por producto en una búsqueda. */
    public const MAX_VARIANT_CARDS = 12;

    /** ¿El nombre de archivo es una foto real? */
    public static function isRealImage($file): bool
    {
        return is_string($file) && trim($file) !== '' && $file !== self::NO_IMAGE;
    }

    /**
     * Restringe una consulta de `items` a los productos con foto propia o con
     * al menos una variante activa con foto. Vale para Eloquent y para el
     * query builder. `$table` es el nombre o alias de `items` en la consulta.
     */
    public static function whereHasImage($query, string $table = 'items')
    {
        return $query->where(function ($q) use ($table) {
            $q->where(function ($w) use ($table) {
                $w->whereNotNull("{$table}.image")
                  ->where("{$table}.image", '<>', '')
                  ->where("{$table}.image", '<>', self::NO_IMAGE);
            })->orWhereExists(function ($sub) use ($table) {
                $sub->select(DB::raw(1))
                    ->from('item_variants as ivimg')
                    ->whereColumn('ivimg.item_id', "{$table}.id")
                    ->where('ivimg.is_active', 1)
                    ->whereNotNull('ivimg.image')
                    ->where('ivimg.image', '<>', '')
                    ->where('ivimg.image', '<>', self::NO_IMAGE);
            });
        });
    }

    /**
     * Foto de respaldo de la tarjeta para los productos SIN foto propia que sí
     * la tienen en alguna variante: [item_id => archivo]. Una consulta para
     * toda la página. Prioriza la variante principal y luego el orden de alta.
     *
     * @param int[] $itemIds
     * @return array<int,string>
     */
    public static function variantImageFallbacks(array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('intval', $itemIds))));
        if ($itemIds === []) {
            return [];
        }
        try {
            $rows = DB::connection('tenant')->table('item_variants')
                ->whereIn('item_id', $itemIds)
                ->where('is_active', 1)
                ->whereNotNull('image')
                ->where('image', '<>', '')
                ->where('image', '<>', self::NO_IMAGE)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get(['item_id', 'image']);
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r->item_id] ??= $r->image;
        }
        return $out;
    }

    /**
     * Rellena `storefront_image` en los productos sin foto propia con la de una
     * variante, para que la tarjeta no salga con el bloque «sin imagen» (el
     * filtro ya los deja pasar porque una variante tiene foto).
     *
     * @param iterable $items modelos Item (o paginator)
     */
    public static function attachVariantImageFallback($items): void
    {
        $sinFoto = [];
        foreach ($items as $it) {
            if (!self::isRealImage($it->image ?? null) && !empty($it->has_variants)) {
                $sinFoto[] = $it->id;
            }
        }
        if ($sinFoto === []) {
            return;
        }
        $fallbacks = self::variantImageFallbacks($sinFoto);
        foreach ($items as $it) {
            if (isset($fallbacks[$it->id]) && !self::isRealImage($it->image ?? null)) {
                $it->storefront_image = $fallbacks[$it->id];
            }
        }
    }

    /**
     * Expande, en la página ya paginada, cada producto con variantes en una
     * tarjeta por variante ACTIVA, CON STOCK y CON FOTO PROPIA.
     *
     * Reglas (las del marketplace, decisión del dueño 2026-11-18):
     *  - Sin texto de búsqueda no se expande: una tarjeta por producto.
     *  - Variantes sin foto propia no salen como tarjeta. Si ninguna tiene
     *    foto (o el producto usa la foto del padre para todas), queda la
     *    tarjeta del producto.
     *  - Si alguna variante casa por nombre o SKU con lo buscado, salen solo
     *    esas; si ninguna casa, todas las que tienen foto y stock, hasta
     *    MAX_VARIANT_CARDS por producto.
     *
     * Cada tarjeta es un clon del producto con `variant_card` (array con id,
     * nombre, foto, precio y oferta según ItemVariant::offerFor()) que la
     * tarjeta usa para el título «<producto> — <variante>», la foto, el precio
     * y el enlace `?variant=<item_variants.id>`. El conteo y la paginación no
     * cambian: el total sigue siendo de productos, como en el marketplace.
     */
    public static function explodeVariantsIntoCards($paginator, ?string $q): void
    {
        if (!$paginator instanceof Paginator || trim((string) $q) === '') {
            return;
        }
        $items = collect($paginator->items());
        $conVariantes = $items->filter(
            fn ($i) => !empty($i->has_variants) && empty($i->use_parent_image_for_variants)
        );
        if ($conVariantes->isEmpty()) {
            return;
        }
        $ids = $conVariantes->pluck('id')->map(fn ($id) => (int) $id)->all();

        try {
            $variantes = ItemVariant::whereIn('item_id', $ids)
                ->where('is_active', 1)
                ->whereNotNull('image')
                ->where('image', '<>', '')
                ->where('image', '<>', self::NO_IMAGE)
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get()
                ->groupBy('item_id');
        } catch (\Throwable $e) {
            return;
        }
        if ($variantes->isEmpty()) {
            return;
        }

        // Stock que se anuncia = físico − comprometido de cada variante
        // activa, sumado por almacén (StockQueryService, la fuente de A02).
        $disponible = [];
        foreach (app(StockQueryService::class)->variantRowsFor($ids) as $filas) {
            foreach ($filas as $f) {
                $disponible[$f['variant_id']] = ($disponible[$f['variant_id']] ?? 0) + $f['available'];
            }
        }

        $tokens  = self::searchTokens($q);
        $grafias = [];
        foreach ($tokens as $t) {
            foreach (\App\Services\System\SearchSynonyms::spellings($t) ?: [$t] as $g) {
                $g = self::normalize($g);
                if ($g !== '') {
                    $grafias[] = $g;
                }
            }
        }
        $grafias = array_values(array_unique($grafias));

        $expandido = collect();
        foreach ($items as $item) {
            $vs = $variantes->get($item->id);
            if (!$vs || empty($item->has_variants) || !empty($item->use_parent_image_for_variants)) {
                $expandido->push($item);
                continue;
            }
            $conStock = $vs->filter(function ($v) use ($disponible) {
                $stock = array_key_exists($v->id, $disponible) ? $disponible[$v->id] : (float) $v->stock;
                return $stock > 0;
            });
            if ($conStock->isEmpty()) {
                $expandido->push($item);
                continue;
            }

            $mostrar = $conStock;
            if ($grafias) {
                $casan = $conStock->filter(fn ($v) => self::variantMatches($v->display_name, $v->sku, $grafias, $tokens));
                if ($casan->isNotEmpty()) {
                    $mostrar = $casan;
                }
            }

            foreach ($mostrar->take(self::MAX_VARIANT_CARDS) as $v) {
                $oferta = $v->offerFor($item);
                $nombre = trim((string) $v->display_name);
                $card = clone $item;
                $card->variant_card = [
                    'id'       => (int) $v->id,
                    'name'     => $nombre,
                    'title'    => ($nombre !== '' && mb_strtolower($nombre) !== mb_strtolower(trim((string) $item->description)))
                        ? $item->description . ' — ' . $nombre
                        : $item->description,
                    'image'    => $v->image,
                    'price'    => (float) $oferta['price'],
                    // Solo la oferta VIGENTE tacha precio.
                    'original' => $oferta['original'] !== null ? (float) $oferta['original'] : 0.0,
                    'stock'    => (float) ($disponible[$v->id] ?? $v->stock),
                ];
                $expandido->push($card);
            }
        }

        if (method_exists($paginator, 'setCollection')) {
            $paginator->setCollection($expandido);
        }
    }

    /**
     * Palabras útiles de la búsqueda (mismo corte que el marketplace: comas y
     * barras separan, palabras de 1 letra fuera salvo que sea lo único).
     *
     * @return string[]
     */
    public static function searchTokens(?string $q): array
    {
        $q = trim(preg_replace('/\s+/', ' ', (string) $q));
        if ($q === '') {
            return [];
        }
        $palabras = preg_split('/[\s,;\/]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_filter($palabras, fn ($t) => mb_strlen($t) >= 2));
        return array_slice($tokens ?: [$q], 0, 5);
    }

    /** Minúsculas y sin tildes, para comparar nombres de variante. */
    public static function normalize(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));
        return strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);
    }

    /**
     * ¿La variante casa con lo buscado? Por nombre: alguna palabra del nombre
     * EMPIEZA por alguna grafía buscada («roj» casa con «Rojo»). Por SKU: el
     * SKU contiene una palabra buscada de 3+ caracteres.
     */
    public static function variantMatches(?string $nombre, ?string $sku, array $grafias, array $tokens): bool
    {
        $palabras = preg_split('/[^\p{L}\p{N}]+/u', self::normalize($nombre), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($palabras as $p) {
            foreach ($grafias as $g) {
                if ($g !== '' && str_starts_with($p, $g)) {
                    return true;
                }
            }
        }
        if ($sku !== null && $sku !== '') {
            $skuNorm = self::normalize($sku);
            foreach ($tokens as $t) {
                $t = self::normalize($t);
                if (mb_strlen($t) >= 3 && str_contains($skuNorm, $t)) {
                    return true;
                }
            }
        }
        return false;
    }
}
