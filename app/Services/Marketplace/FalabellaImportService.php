<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\Item;
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

        // 1) ¿Ya está enlazado este SellerSku en este canal?
        $existingMapping = MarketplaceProduct::where('channel_id', $this->channel->id)
            ->where('external_sku', $sku)
            ->first();
        if ($existingMapping) {
            return ['sku' => $sku, 'action' => 'skipped', 'name' => $name, 'reason' => 'ya enlazado'];
        }

        // 2) ¿Ya existe un item con ese SellerSku (item_code)? → enlazar sin crear.
        $item = Item::where('item_code', $sku)->first();
        $action = $item ? 'linked' : 'created';

        // Datos de negocio (precio/stock) vienen en BusinessUnits.
        $bu = data_get($p, 'BusinessUnits.BusinessUnit');
        if (isset($bu[0])) $bu = $bu[0]; // si hay varias unidades de negocio, tomar la primera
        $price = (float) (data_get($bu, 'Price') ?: data_get($bu, 'SpecialPrice') ?: 0);
        $stock = (int) (data_get($bu, 'Stock') ?: 0);

        if ($dryRun) {
            return [
                'sku' => $sku, 'action' => $action, 'name' => $name,
                'price' => $price, 'stock' => $stock,
                'brand' => data_get($p, 'Brand'), 'category' => data_get($p, 'PrimaryCategory'),
            ];
        }

        $isNew = !$item;
        if (!$item) {
            $item = $this->createItem($p, $sku, $name, $price, $stock);
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

        // 5) Imágenes (opcional, lento)
        if ($this->withImages && $action === 'created') {
            $this->importMainImage($item, $p, $name);
        }

        return ['sku' => $sku, 'action' => $action, 'name' => $name, 'item_id' => $item->id, 'price' => $price, 'stock' => $stock];
    }

    protected function createItem(array $p, string $sku, string $name, float $price, int $stock): Item
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
     * Descarga la imagen principal de Saga y genera las variantes locales.
     * Si algo falla, deja el placeholder (no rompe la importación).
     */
    protected function importMainImage(Item $item, array $p, string $name): void
    {
        $url = (string) data_get($p, 'MainImage') ?: (string) data_get($p, 'Images.Image.0');
        if ($url === '') return;

        $tmp = null;
        try {
            $resp = Http::timeout(25)->get($url);
            if (!$resp->successful()) return;

            $tmp = tempnam(sys_get_temp_dir(), 'saga_img_');
            file_put_contents($tmp, $resp->body());

            $base = ImageProcessingService::sanitizeFilename($name, $item->internal_id ?: 'product');
            $result = ImageProcessingService::processAndStore($tmp, $base);

            $item->image = $result['main'] ?? $item->image;
            $item->image_medium = $result['medium'] ?? $item->image_medium;
            $item->image_small = $result['small'] ?? $item->image_small;
            $item->saveQuietly();
        } catch (\Throwable $e) {
            Log::channel('payments')->warning("Falabella import image fail [{$item->item_code}]: {$e->getMessage()}");
        } finally {
            if ($tmp && file_exists($tmp)) @unlink($tmp);
        }
    }
}
