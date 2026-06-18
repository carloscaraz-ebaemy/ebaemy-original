<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\Item;
use App\Models\Tenant\ItemImage;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Services\Tenant\ImageProcessingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Models\Warehouse;
use Modules\Item\Models\Brand;
use Modules\Item\Models\Category;

/**
 * Importa el catálogo del seller desde Saga Falabella HACIA EBAEMY.
 *
 * Caso de uso: el seller ya tiene sus productos creados en el Seller Center de
 * Saga pero NO existen en EBAEMY. Este servicio los trae (GetProducts), crea el
 * `Item` con su categoría/marca/precio/stock, descarga imágenes (opcional) y
 * crea el enlace `MarketplaceProduct` (external_sku = SellerSku) para que el
 * sync de stock/precio posterior funcione.
 *
 * Idempotente: si el SellerSku ya está enlazado o ya existe un item con ese
 * `item_code`, no duplica — sólo asegura el enlace.
 */
class FalabellaImportService
{
    protected MarketplaceChannel $channel;
    protected FalabellaService $api;
    protected ?Warehouse $warehouse;
    protected bool $withImages;

    public function __construct(MarketplaceChannel $channel, bool $withImages = false)
    {
        $this->channel = $channel;
        $this->api = new FalabellaService($channel);
        $this->withImages = $withImages;

        // Preferir el almacén principal del establecimiento del usuario autenticado
        // (la importación corre autenticada como admin del tenant); si no, el primero.
        $estId = optional(optional(auth()->user())->establishment)->id;
        $this->warehouse = ($estId
            ? Warehouse::where('establishment_id', $estId)->first()
            : null) ?? Warehouse::first();
    }

    /**
     * Ejecuta la importación.
     *
     * @param  bool  $dryRun   Si true, NO escribe nada: solo reporta qué haría.
     * @param  int   $limit    Máximo de productos a traer de Saga en esta tanda.
     * @param  int   $offset   Desde qué posición traer (paginación por lotes).
     * @return array  Resumen { fetched, created, linked, skipped, failed, rows[] }
     */
    public function import(bool $dryRun = false, int $limit = 1000, int $offset = 0): array
    {
        if (!$this->warehouse && !$dryRun) {
            throw new \RuntimeException('El tenant no tiene ningún almacén (warehouse) configurado.');
        }

        $params = ['Limit' => $limit];
        if ($offset > 0) {
            $params['Offset'] = $offset;
        }
        $products = $this->api->getProducts($params);

        $summary = [
            'fetched' => count($products),
            'created' => 0,
            'linked'  => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed'  => 0,
            'rows'    => [],
        ];

        foreach ($products as $p) {
            $sku = trim((string) data_get($p, 'SellerSku', ''));
            if ($sku === '') {
                $summary['skipped']++;
                continue;
            }

            try {
                $row = $this->importOne($p, $sku, $dryRun);
                $summary[$row['action']]++; // created | linked | skipped
                $summary['rows'][] = $row;
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['rows'][] = ['sku' => $sku, 'action' => 'failed', 'name' => data_get($p, 'Name'), 'error' => $e->getMessage()];
                Log::channel('payments')->error("Falabella import error [{$sku}]: {$e->getMessage()}");
            }
        }

        return $summary;
    }

    /**
     * Importa un producto individual. Devuelve la fila de resumen.
     */
    protected function importOne(array $p, string $sku, bool $dryRun): array
    {
        $name = trim((string) data_get($p, 'Name', $sku));

        // Datos de negocio (precio/stock) vienen en BusinessUnits. Se calculan
        // primero para poder actualizar precios de productos ya importados.
        $bu = data_get($p, 'BusinessUnits.BusinessUnit');
        if (isset($bu[0])) $bu = $bu[0]; // si hay varias unidades de negocio, tomar la primera

        // Precio efectivo (oferta si está activa) + precio tachado + duración (from/until).
        [$price, $compareAt, $until, $from] = $this->resolvePrices($bu);
        $stock = (int) (data_get($bu, 'Stock') ?: 0);

        // 1) ¿Ya está enlazado este SellerSku? → ACTUALIZAR precios desde Saga
        //    (Saga es la fuente de verdad de precios/ofertas; no tocamos el stock).
        $existingMapping = MarketplaceProduct::where('channel_id', $this->channel->id)
            ->where('external_sku', $sku)
            ->first();
        if ($existingMapping) {
            if (!$dryRun) {
                $existing = Item::find($existingMapping->item_id);
                if ($existing) {
                    $existing->sale_unit_price = $price;
                    $existing->compare_at_price = $compareAt;
                    $existing->compare_at_from = $from;
                    $existing->compare_at_until = $until;
                    $existing->saveQuietly();

                    // Backfill de imágenes: los productos ya importados entraron
                    // con solo 1 imagen; aquí se completa la galería (idempotente).
                    if ($this->withImages) {
                        $this->importImages($existing, $p, $name, false);
                    }
                }
            }
            return ['sku' => $sku, 'action' => 'updated', 'name' => $name, 'price' => $price, 'compare_at' => $compareAt, 'stock' => $stock];
        }

        // 2) ¿Ya existe un item con ese SellerSku (item_code)? → enlazar sin crear.
        $item = Item::where('item_code', $sku)->first();
        $action = $item ? 'linked' : 'created';

        if ($dryRun) {
            return [
                'sku' => $sku, 'action' => $action, 'name' => $name,
                'price' => $price, 'compare_at' => $compareAt, 'stock' => $stock,
                'brand' => data_get($p, 'Brand'), 'category' => data_get($p, 'PrimaryCategory'),
            ];
        }

        $isNew = !$item;
        if (!$item) {
            $item = $this->createItem($p, $sku, $name, $price, $compareAt, $from, $until, $stock);
        } else {
            // El item existía sin enlace → actualizar también sus precios.
            $item->sale_unit_price = $price;
            $item->compare_at_price = $compareAt;
            $item->compare_at_from = $from;
            $item->compare_at_until = $until;
            $item->saveQuietly();
        }

        // 3) Stock por almacén. Solo sembramos el stock de Saga en items NUEVOS;
        //    en items ya existentes NO pisamos su stock real.
        if ($isNew) {
            $this->seedWarehouseStock($item, $stock);
        }

        // 4) Enlace marketplace (external_sku = SellerSku). Estado 'synced'
        //    porque el producto YA vive en Saga (no hay que crearlo allá).
        MarketplaceProduct::updateOrCreate(
            ['channel_id' => $this->channel->id, 'item_id' => $item->id, 'item_variant_id' => null],
            [
                'external_sku' => $sku,
                'external_id'  => (string) data_get($p, 'ShopSku', ''),
                'sync_status'  => 'synced',
                'synced_at'    => now(),
            ]
        );

        // 5) Imágenes (opcional, lento). Trae principal + galería. Idempotente:
        // permite backfill de la galería en productos ya importados (re-correr
        // con --with-images sin duplicar imágenes).
        if ($this->withImages) {
            $this->importImages($item, $p, $name, $action === 'created');
        }

        return ['sku' => $sku, 'action' => $action, 'name' => $name, 'item_id' => $item->id, 'price' => $price, 'stock' => $stock];
    }

    /**
     * Resuelve el precio de venta y el precio tachado desde la BusinessUnit de Saga.
     * Si hay oferta (SpecialPrice) vigente → venta = oferta, tachado = precio regular.
     * Si no → venta = precio regular, sin tachado.
     *
     * @return array{0: float, 1: ?float, 2: ?string}  [salePrice, compareAtPrice, untilDate]
     */
    protected function resolvePrices($bu): array
    {
        $regular = (float) (data_get($bu, 'Price') ?: 0);
        $special = (float) (data_get($bu, 'SpecialPrice') ?: 0);

        if ($special > 0 && $special < $regular && $this->offerActive($bu)) {
            // venta = oferta, tachado = regular, duración = SpecialFromDate..SpecialToDate
            $until = $from = null;
            try {
                $to = data_get($bu, 'SpecialToDate');
                $until = $to ? \Illuminate\Support\Carbon::parse($to)->toDateString() : null;
                $fd = data_get($bu, 'SpecialFromDate');
                $from = $fd ? \Illuminate\Support\Carbon::parse($fd)->toDateString() : null;
            } catch (\Throwable $e) {}
            return [$special, $regular, $until, $from];
        }

        return [$regular ?: $special, null, null, null];
    }

    /**
     * ¿La oferta (SpecialPrice) está vigente según SpecialFromDate / SpecialToDate?
     * Si no hay fechas, se considera vigente.
     */
    protected function offerActive($bu): bool
    {
        $from = data_get($bu, 'SpecialFromDate');
        $to   = data_get($bu, 'SpecialToDate');

        try {
            if ($from && now()->lt(\Illuminate\Support\Carbon::parse($from))) return false;
            if ($to && now()->gt(\Illuminate\Support\Carbon::parse($to)))   return false;
        } catch (\Throwable $e) {
            // Fecha inválida → tratar la oferta como vigente.
        }

        return true;
    }

    protected function createItem(array $p, string $sku, string $name, float $price, ?float $compareAt, ?string $from, ?string $until, int $stock): Item
    {
        $category = $this->resolveCategory((string) data_get($p, 'PrimaryCategory', ''));
        $brand    = $this->resolveBrand((string) data_get($p, 'Brand', ''));
        $variation = trim((string) data_get($p, 'Variation', ''));
        $ean       = trim((string) data_get($p, 'ProductId', ''));
        $rawDesc   = (string) data_get($p, 'Description', '');

        $item = new Item();
        $item->description = mb_substr($name, 0, 600);
        $item->name = mb_substr(trim($name . ($variation ? " {$variation}" : '')), 0, 600);
        $item->item_type_id = '01';                 // Producto
        $item->unit_type_id = 'NIU';                // Unidades
        $item->currency_type_id = 'PEN';            // Soles
        $item->sale_unit_price = $price;
        $item->compare_at_price = $compareAt; // precio tachado (regular) cuando hay oferta
        $item->compare_at_from = $from;       // inicio de la oferta (SpecialFromDate de Saga)
        $item->compare_at_until = $until;     // fin de la oferta (SpecialToDate de Saga)
        $item->purchase_unit_price = 0;
        $item->sale_affectation_igv_type_id = '10';     // Gravado
        $item->purchase_affectation_igv_type_id = '10';
        $item->has_igv = true;
        $item->purchase_has_igv = true;
        $item->category_id = $category?->id;
        $item->brand_id = $brand?->id;
        $item->item_code = $sku;                     // SellerSku completo
        $item->item_code_gs1 = $ean ?: null;         // EAN / código de barras
        $item->stock = $stock;
        $item->stock_min = 0;
        $item->active = true;
        $item->image = 'imagen-no-disponible.jpg';
        $item->image_medium = 'imagen-no-disponible.jpg';
        $item->image_small = 'imagen-no-disponible.jpg';
        $item->apply_store = true;                   // visible en la tienda del tenant
        $item->mp_notes = mb_substr($rawDesc, 0, 5000) ?: null;
        $item->save();

        // internal_id con la convención del ERP (id con padding)
        $item->internal_id = str_pad((string) $item->id, 5, '0', STR_PAD_LEFT);
        $item->saveQuietly();

        return $item;
    }

    protected function seedWarehouseStock(Item $item, int $stock): void
    {
        if (!$this->warehouse) return;

        // El observer de Item suele pre-crear la fila item_warehouse en 0 al guardar.
        // firstOrNew la recupera (o crea) y sembramos el stock inicial de Saga.
        $iw = ItemWarehouse::firstOrNew([
            'item_id' => $item->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $iw->stock = $stock;
        $iw->stock_physical = $stock;
        $iw->stock_committed = (float) ($iw->stock_committed ?? 0);
        $iw->save();
    }

    protected function resolveCategory(string $name): ?Category
    {
        $name = trim($name) ?: 'General';
        $cat = Category::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        return $cat ?: Category::create(['name' => $name]);
    }

    protected function resolveBrand(string $name): ?Brand
    {
        $name = trim($name) ?: 'Genérica';
        $brand = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        return $brand ?: Brand::create(['name' => $name]);
    }

    /**
     * Importa TODAS las imágenes del producto de Saga: la principal (en
     * items.image/medium/small) y el resto en la galería (item_images).
     * Idempotente: solo siembra la galería si el item aún no tiene imágenes
     * adicionales (permite backfill sin duplicar). No rompe la importación.
     */
    protected function importImages(Item $item, array $p, string $name, bool $isNew): void
    {
        // Normaliza la lista de URLs de Saga (puede venir como array o string).
        $raw = data_get($p, 'Images.Image', []);
        if (is_string($raw)) $raw = [$raw];
        $urls = array_values(array_filter(array_map(fn($u) => (string) $u, (array) $raw)));

        $mainUrl = (string) data_get($p, 'MainImage') ?: ($urls[0] ?? '');
        if ($mainUrl === '' && empty($urls)) return;

        // 1) Imagen principal: si es nuevo o aún no tiene imagen real.
        $needsMain = $isNew || empty($item->image) || $item->image === 'imagen-no-disponible.jpg';
        if ($mainUrl !== '' && $needsMain) {
            $result = $this->downloadAndProcess($mainUrl, $name, $item);
            if ($result) {
                $item->image = $result['main'] ?? $item->image;
                $item->image_medium = $result['medium'] ?? $item->image_medium;
                $item->image_small = $result['small'] ?? $item->image_small;
                $item->saveQuietly();
            }
        }

        // 2) Galería: solo si el item aún no tiene imágenes adicionales (evita
        // duplicar al re-correr). Excluye la principal y limita a 8 por seguridad.
        if ($item->images()->count() === 0) {
            $gallery = array_values(array_filter($urls, fn($u) => $u !== $mainUrl));
            foreach ($gallery as $i => $gurl) {
                if ($i >= 8) break;
                $result = $this->downloadAndProcess($gurl, $name . '-' . ($i + 2), $item);
                if ($result && !empty($result['main'])) {
                    ItemImage::create(['item_id' => $item->id, 'image' => $result['main']]);
                }
            }
        }
    }

    /**
     * Descarga una URL de imagen y genera las variantes locales. Devuelve
     * ['main','medium','small'] o null si falla.
     */
    protected function downloadAndProcess(string $url, string $name, Item $item): ?array
    {
        if ($url === '') return null;
        $tmp = null;
        try {
            $resp = Http::timeout(25)->get($url);
            if (!$resp->successful()) return null;

            $tmp = tempnam(sys_get_temp_dir(), 'saga_img_');
            file_put_contents($tmp, $resp->body());

            $base = ImageProcessingService::sanitizeFilename($name, $item->internal_id ?: 'product');
            return ImageProcessingService::processAndStore($tmp, $base);
        } catch (\Throwable $e) {
            Log::channel('payments')->warning("Falabella import image fail [{$item->item_code}]: {$e->getMessage()}");
            return null;
        } finally {
            if ($tmp && file_exists($tmp)) @unlink($tmp);
        }
    }
}
