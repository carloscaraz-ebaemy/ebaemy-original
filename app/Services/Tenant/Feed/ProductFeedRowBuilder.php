<?php

namespace App\Services\Tenant\Feed;

use App\Models\Tenant\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aplana el catálogo de la tienda en filas de feed, UNA POR VARIANTE.
 *
 * Meta, Google y TikTok no tienen el concepto de "producto con variantes":
 * cada color o talla es un item independiente del catálogo, y lo único que
 * los agrupa para que el anuncio muestre un selector es `item_group_id`.
 * Hasta ahora los feeds del tenant emitían una sola fila por producto padre
 * —precio mínimo, stock sumado, primera foto—, así que en el catálogo de
 * Facebook, en Instagram Shopping y en el catálogo de WhatsApp sólo existía
 * el producto principal: las variantes no eran comprables.
 *
 * Esta clase es el único sitio que decide qué es un item de feed, para que
 * los cuatro feeds (Google, Facebook, TikTok, CSV genérico) no divergan.
 */
class ProductFeedRowBuilder
{
    /** Imagen placeholder del sistema: no debe viajar como imagen real. */
    private const NO_IMAGE = 'imagen-no-disponible.jpg';

    private string $base;
    private string $storeName;
    private string $currency;

    public function __construct(string $base, string $storeName, string $currency = 'PEN')
    {
        $this->base      = rtrim($base, '/');
        $this->storeName = $storeName;
        $this->currency  = $currency;
    }

    /**
     * @return Collection<int, array>  filas normalizadas, listas para cualquier formato
     */
    public function rows(): Collection
    {
        $items = Item::where('apply_store', 1)
            ->with([
                'category:id,name',
                'brand:id,name',
                'warehouses',
                'images',
                'variants',
                'variants.optionValues',
                'variants.optionValues.option:id,name',
            ])
            ->select([
                'id', 'slug', 'description', 'name', 'mp_notes', 'image',
                'sale_unit_price', 'sale_unit_price_set', 'is_set', 'has_variants',
                'currency_type_id', 'updated_at', 'stock', 'internal_id',
                'category_id', 'brand_id', 'use_parent_image_for_variants',
            ])
            ->get();

        $variantStock = $this->variantPhysicalStock(
            $items->where('has_variants', true)->pluck('id')->all()
        );

        $rows = collect();

        foreach ($items as $item) {
            $variants = $item->has_variants ? $item->variants : collect();

            // has_variants=1 sin variantes activas sigue siendo un producto
            // vendible: cae a la fila del padre en vez de desaparecer del feed.
            if ($variants->isEmpty()) {
                $rows->push($this->parentRow($item));
                continue;
            }

            foreach ($variants as $variant) {
                $rows->push($this->variantRow($item, $variant, $variantStock[$variant->id] ?? 0.0));
            }
        }

        return $rows;
    }

    // ── Filas ──────────────────────────────────────────────────────────────

    private function parentRow(Item $item): array
    {
        $price = $item->is_set && $item->sale_unit_price_set
            ? (float) $item->sale_unit_price_set
            : (float) $item->sale_unit_price;

        $stock = 0.0;
        foreach ($item->warehouses as $wh) {
            $stock += (float) $wh->stock;
        }

        return $this->row($item, [
            'id'         => (string) $item->id,
            'group_id'   => (string) $item->id,
            'title'      => (string) $item->description,
            'price'      => $price,
            'inventory'  => $stock,
            'image_link' => $this->itemImage($item),
            'link'       => $this->itemLink($item),
            'sku'        => (string) ($item->internal_id ?: ''),
            'title_base' => (string) $item->description,
            'title_tail' => '',
        ]);
    }

    private function variantRow(Item $item, $variant, float $stock): array
    {
        [$color, $size, $suffix] = $this->variantAttributes($variant);

        // Precio: null en la variante significa "hereda del padre".
        $price = (float) ($variant->sale_unit_price ?: $item->sale_unit_price);

        // Imagen: la propia de la variante salvo que el producto pida
        // explícitamente usar la del padre para todas.
        $image = $this->itemImage($item);
        if (!$item->use_parent_image_for_variants && $this->usable($variant->image)) {
            $image = $this->uploadUrl($variant->image);
        }

        return $this->row($item, [
            'id'         => $item->id . '-v' . $variant->id,
            'group_id'   => (string) $item->id,
            'title'      => $suffix ? $item->description . ' - ' . $suffix : (string) $item->description,
            'price'      => $price,
            'inventory'  => $stock,
            'image_link' => $image,
            // El clic del anuncio debe caer en la variante anunciada, no en el
            // producto con todo por elegir otra vez.
            'link'       => $this->itemLink($item) . '?variant=' . $variant->id,
            'sku'        => (string) ($variant->sku ?: $item->internal_id . '-V' . $variant->id),
            'color'      => $color,
            'size'       => $size,
            'variant_id' => $variant->id,
            'title_base' => (string) $item->description,
            'title_tail' => $suffix,
        ]);
    }

    /** Campos comunes a padre y variante. */
    private function row(Item $item, array $own): array
    {
        $description = $item->mp_notes ?: ($item->name ?: $item->description);

        return array_merge([
            'item_id'      => $item->id,
            'variant_id'   => null,
            'color'        => null,
            'size'         => null,
            'description'  => trim(strip_tags((string) $description)),
            'brand'        => $item->brand->name ?: $this->storeName,
            'category'     => $item->category->name ?: 'General',
            'currency'     => $this->currency,
            'extra_images' => $this->extraImages($item),
            'updated_at'   => $item->updated_at,
        ], $own);
    }

    // ── Atributos de variante ──────────────────────────────────────────────

    /**
     * Separa color y talla de las opciones de la variante. Meta sólo entiende
     * `color` y `size` como atributos de agrupación; el resto va al título.
     *
     * @return array{0:?string,1:?string,2:string}  [color, size, sufijo del título]
     */
    private function variantAttributes($variant): array
    {
        $color = null;
        $size  = null;
        $parts = [];

        foreach ($variant->optionValues as $value) {
            $optionName = mb_strtolower((string) ($value->option->name ?? ''));
            $parts[]    = $value->value;

            if (str_contains($optionName, 'color')) {
                $color = $value->value;
            } elseif (str_contains($optionName, 'talla') || str_contains($optionName, 'size')
                   || str_contains($optionName, 'tama')  || str_contains($optionName, 'medida')) {
                $size = $value->value;
            }
        }

        $suffix = $variant->display_name ?: implode(' / ', array_filter($parts));

        return [$color, $size, (string) $suffix];
    }

    // ── Imágenes y enlaces ─────────────────────────────────────────────────

    private function usable($image): bool
    {
        return $image && $image !== self::NO_IMAGE;
    }

    private function uploadUrl(string $image): string
    {
        return asset('storage/uploads/items/' . $image);
    }

    private function itemImage(Item $item): string
    {
        if ($this->usable($item->image)) {
            return $this->uploadUrl($item->image);
        }

        // Padre sin foto (caso típico cuando la foto vive en cada variante).
        $fromVariant = $item->variants->pluck('image')->first(fn ($img) => $this->usable($img));
        if ($fromVariant) {
            return $this->uploadUrl($fromVariant);
        }

        return asset('logo/' . self::NO_IMAGE);
    }

    private function extraImages(Item $item): array
    {
        if (!$item->relationLoaded('images') || !$item->images) {
            return [];
        }

        return $item->images
            ->pluck('image')
            ->filter(fn ($img) => $this->usable($img))
            ->take(5)
            ->map(fn ($img) => $this->uploadUrl($img))
            ->values()
            ->all();
    }

    private function itemLink(Item $item): string
    {
        return $this->base . '/item/' . ($item->slug ?: $item->id);
    }

    // ── Stock ──────────────────────────────────────────────────────────────

    /**
     * Stock físico por variante desde item_variant_warehouse.
     *
     * No se usa item_variants.stock: es un derivado que puede ir desfasado, y
     * un stock falso en el feed se traduce en ventas de lo que no hay o en
     * variantes marcadas agotadas que sí están.
     *
     * @return array<int, float>  variant_id => stock
     */
    private function variantPhysicalStock(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        return DB::connection('tenant')
            ->table('item_variants as iv')
            ->leftJoin('item_variant_warehouse as ivw', 'ivw.item_variant_id', '=', 'iv.id')
            ->whereIn('iv.item_id', $itemIds)
            ->where('iv.is_active', 1)
            ->groupBy('iv.id')
            ->selectRaw('iv.id, COALESCE(SUM(ivw.stock_physical), 0) AS stock')
            ->pluck('stock', 'id')
            ->map(fn ($s) => (float) $s)
            ->all();
    }

    // ── Formateo compartido ────────────────────────────────────────────────

    public function formatPrice(array $row): string
    {
        return number_format((float) $row['price'], 2, '.', '') . ' ' . $row['currency'];
    }

    public function availability(array $row): string
    {
        return $row['inventory'] > 0 ? 'in stock' : 'out of stock';
    }

    /**
     * Título recortado SIN perder el nombre de la variante.
     *
     * Recortar el título entero deja "Pino Artificial (3 Cuerpos) -" en todas
     * las variantes: el sufijo —lo único que las distingue— es justo lo que
     * cae al final. Se recorta el nombre del producto y se conserva el
     * sufijo completo.
     */
    public function title(array $row, int $limit = 150): string
    {
        $base = $row['title_base'] ?? $row['title'];
        $tail = trim((string) ($row['title_tail'] ?? ''));

        if ($tail === '') {
            return Str::limit($base, $limit, '');
        }

        $sep  = ' - ';
        $room = $limit - mb_strlen($tail) - mb_strlen($sep);

        // Sufijo tan largo que no deja sitio al producto: manda la variante.
        if ($room < 12) {
            return Str::limit($tail, $limit, '');
        }

        return rtrim(Str::limit($base, $room, '')) . $sep . $tail;
    }

    public function description(array $row, int $limit = 5000): string
    {
        return Str::limit($row['description'] ?: $row['title'], $limit, '');
    }
}
