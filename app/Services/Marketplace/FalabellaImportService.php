<?php

namespace App\Services\Marketplace;

use App\Jobs\Marketplace\ImportSagaProductImagesJob;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemWarehouse;
use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\SagaCategoryMap;
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
    protected bool $deferImages;
    protected int $imagesQueued = 0;
    /** Avisos por producto: entró, pero con una salvedad que el usuario debe ver. */
    protected array $warnings = [];
    /** Índice de homologación de categorías Saga → ERP (null = sin cargar). */
    protected ?array $categoryMap = null;
    /** ¿Saga manda sobre nombre/marca/categoría al re-importar? (ajuste del canal) */
    protected bool $resyncContent;

    /**
     * @param  bool  $withImages   Traer también las imágenes del producto.
     * @param  bool  $deferImages  Encolar la descarga en vez de hacerla aquí.
     *   Obligatorio cuando el importador corre dentro de una petición HTTP: la
     *   descarga + reencode de hasta 9 imágenes por producto no cabe en el
     *   timeout del servidor y tumbaba el lote entero.
     */
    public function __construct(MarketplaceChannel $channel, bool $withImages = false, bool $deferImages = false)
    {
        $this->channel = $channel;
        $this->api = new FalabellaService($channel);
        $this->withImages = $withImages;
        $this->deferImages = $deferImages;
        // Por defecto NO: el tenant edita sus productos y una re-importación no
        // debe pisarle el nombre o la categoría sin que lo haya pedido.
        $this->resyncContent = (bool) data_get($channel->settings, 'resync_from_saga', false);

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

        // Silencia el auto-publish a Saga durante la importación: traer productos
        // DE Saga no debe re-publicarlos (evita bucle de retroalimentación).
        $obsPrev = \App\Observers\MarketplaceItemObserver::$enabled;
        \App\Observers\MarketplaceItemObserver::$enabled = false;
        try {
            $params = ['Limit' => $limit];
        if ($offset > 0) {
            $params['Offset'] = $offset;
        }
        $products = $this->api->getProducts($params);

        $this->imagesQueued = 0;
        $this->warnings = [];

        $summary = [
            'fetched'       => count($products),
            'created'       => 0,
            'linked'        => 0,
            'updated'       => 0,
            'skipped'       => 0,
            'failed'        => 0,
            'images_queued' => 0,
            'rows'          => [],
            // Productos que SÍ entraron pero con una salvedad (sin precio en
            // Saga, enlace roto, no publicable). Antes no se sabía nunca.
            'warnings'      => [],
            // Detalle de lo que NO entró, para que el panel pueda mostrarlo.
            // Antes sólo existía el contador y el motivo moría en el log.
            'failures'      => [],
        ];

        foreach ($products as $p) {
            $sku = trim((string) data_get($p, 'SellerSku', ''));
            if ($sku === '') {
                $summary['skipped']++;
                $summary['failures'][] = [
                    'sku'   => '(sin SellerSku)',
                    'name'  => (string) data_get($p, 'Name', ''),
                    'error' => 'Saga devolvió el producto sin SellerSku; no hay forma de identificarlo.',
                ];
                continue;
            }

            try {
                $row = $this->importOne($p, $sku, $dryRun);
                $summary[$row['action']]++; // created | linked | skipped
                $summary['rows'][] = $row;
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['rows'][] = ['sku' => $sku, 'action' => 'failed', 'name' => data_get($p, 'Name'), 'error' => $e->getMessage()];
                $summary['failures'][] = [
                    'sku'   => $sku,
                    'name'  => (string) data_get($p, 'Name', ''),
                    'error' => $e->getMessage(),
                ];
                Log::channel('payments')->error("Falabella import error [{$sku}]: {$e->getMessage()}");
            }
        }

            $summary['images_queued'] = $this->imagesQueued;
            $summary['warnings'] = $this->warnings;

            return $summary;
        } finally {
            \App\Observers\MarketplaceItemObserver::$enabled = $obsPrev;
        }
    }

    /**
     * Importa un producto individual. Devuelve la fila de resumen.
     */
    protected function importOne(array $p, string $sku, bool $dryRun): array
    {
        $name = trim((string) data_get($p, 'Name', $sku));

        // Datos de negocio (precio/stock) vienen en la unidad de negocio de
        // Falabella — no en "la primera que venga" (un seller puede tener
        // también Sodimac/Tottus, con otro precio y otro stock).
        $bu = $this->resolveBusinessUnit($p);

        // Precio efectivo (oferta si está activa) + precio tachado + duración (from/until).
        [$price, $compareAt, $until, $from] = $this->resolvePrices($bu);
        $stock = (int) (data_get($bu, 'Stock') ?: 0);
        $status = $this->resolveStatus($p, $bu);

        // 1) ¿Ya está enlazado este SellerSku? → ACTUALIZAR precios desde Saga
        //    (Saga es la fuente de verdad de precios/ofertas; no tocamos el stock).
        $existingMapping = MarketplaceProduct::where('channel_id', $this->channel->id)
            ->where('external_sku', $sku)
            ->first();
        if ($existingMapping) {
            $existing = Item::find($existingMapping->item_id);

            if ($existing) {
                if (!$dryRun) {
                    // Precio 0 = Saga no mandó precio. Pisarlo borraba el precio
                    // real del producto en la tienda del tenant.
                    if ($price > 0) {
                        $existing->sale_unit_price = $price;
                        $existing->compare_at_price = $compareAt;
                        $existing->compare_at_from = $from;
                        $existing->compare_at_until = $until;
                        $existing->saveQuietly();
                    } else {
                        $this->warn($sku, $name, 'Saga no envió precio para este SKU: se conservó el precio que ya tenía el producto.');
                    }

                    $this->resyncFromSaga($existing, $p, $name);

                    // Backfill de imágenes: los productos ya importados entraron
                    // con solo 1 imagen; aquí se completa la galería (idempotente).
                    $this->handleImages($existing, $p, $name, false);
                }

                return ['sku' => $sku, 'action' => 'updated', 'name' => $name, 'price' => $price, 'compare_at' => $compareAt, 'stock' => $stock];
            }

            // Enlace huérfano: el producto se borró en EBAEMY pero el enlace quedó.
            // Antes esto se contaba como "actualizado" sin hacer nada y el SKU no
            // se volvía a importar NUNCA. Ahora se limpia y sigue el flujo normal.
            if (!$dryRun) {
                $existingMapping->delete();
            }
            $this->warn($sku, $name, 'El enlace apuntaba a un producto borrado; se vuelve a crear el producto.');
        }

        // 2) ¿Ya existe un item con ese SellerSku (item_code)? → enlazar sin crear.
        $item = Item::where('item_code', $sku)->first();
        $action = $item ? 'linked' : 'created';

        // Sin precio no se crea: antes entraba a la tienda a S/ 0.00.
        if (!$item && $price <= 0) {
            throw new \RuntimeException('Saga no envió precio (Price/SpecialPrice) para este SKU; no se crea un producto sin precio.');
        }

        if ($dryRun) {
            return [
                'sku' => $sku, 'action' => $action, 'name' => $name,
                'price' => $price, 'compare_at' => $compareAt, 'stock' => $stock,
                'brand' => data_get($p, 'Brand'), 'category' => data_get($p, 'PrimaryCategory'),
            ];
        }

        $isNew = !$item;
        if (!$item) {
            $item = $this->createItem($p, $sku, $name, $price, $compareAt, $from, $until, $stock, $status);
        } elseif ($price > 0) {
            // El item existía sin enlace → actualizar también sus precios.
            $item->sale_unit_price = $price;
            $item->compare_at_price = $compareAt;
            $item->compare_at_from = $from;
            $item->compare_at_until = $until;
            $item->saveQuietly();
        } else {
            $this->warn($sku, $name, 'Saga no envió precio para este SKU: se conservó el precio que ya tenía el producto.');
        }

        if (!$isNew) {
            $this->resyncFromSaga($item, $p, $name);
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
        $this->handleImages($item, $p, $name, $action === 'created');

        return ['sku' => $sku, 'action' => $action, 'name' => $name, 'item_id' => $item->id, 'price' => $price, 'stock' => $stock];
    }

    /**
     * Elige la unidad de negocio correcta del producto.
     *
     * Un seller puede vender en varias unidades del grupo (Falabella, Sodimac,
     * Tottus) y cada una trae SU precio y SU stock. Tomar siempre la primera del
     * array traía los datos de la tienda equivocada.
     */
    protected function resolveBusinessUnit(array $p)
    {
        $bu = data_get($p, 'BusinessUnits.BusinessUnit');

        if (!isset($bu[0])) {
            return $bu; // una sola unidad: viene como objeto suelto
        }

        foreach ($bu as $unit) {
            $code = mb_strtolower((string) (
                data_get($unit, 'OperatorCode')
                ?: data_get($unit, 'BusinessUnit')
                ?: data_get($unit, 'Name')
                ?: ''
            ));
            // 'fa…' es el prefijo de los operadores Falabella (fape en Perú).
            if (str_contains($code, 'falabella') || str_starts_with($code, 'fa')) {
                return $unit;
            }
        }

        return $bu[0];
    }

    /**
     * Estado del producto en Saga. Puede venir en el producto o en su unidad
     * de negocio, según la acción de la API.
     */
    protected function resolveStatus(array $p, $bu): string
    {
        return mb_strtolower(trim((string) (
            data_get($p, 'Status')
            ?: data_get($bu, 'Status')
            ?: ''
        )));
    }

    /**
     * ¿Se puede publicar en la tienda del tenant? Sólo se bloquea lo que Saga
     * marca explícitamente como no vendible; un estado desconocido o vacío se
     * trata como publicable (no castigar datos que no entendemos).
     *
     * Ojo: 'sold-out' NO bloquea — es un producto vivo sin stock.
     */
    protected function isPublishable(string $status): bool
    {
        if ($status === '') {
            return true;
        }

        foreach (['inactive', 'deleted', 'reject', 'disapprov'] as $blocked) {
            if (str_contains($status, $blocked)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Re-sincroniza desde Saga el contenido de un producto que YA existe:
     * nombre, descripción, marca y categoría. Apagado por defecto — se activa
     * por canal con el ajuste `resync_from_saga` (botón «Saga manda» del panel).
     *
     * Deliberadamente NO toca:
     *  - el stock, que es el inventario real del tenant (Saga solo refleja lo
     *    que el propio tenant le empujó);
     *  - `active` / `apply_store`, que son decisión del tenant en su tienda;
     *  - `item_code`, que es la llave del enlace.
     *
     * Usa save() y no saveQuietly() a propósito: el ItemObserver mantiene
     * `text_filter`, el índice de búsqueda del ERP, que quedaría desfasado si
     * cambiamos el nombre por detrás. El auto-publish a Saga está silenciado
     * durante toda la importación, así que no hay bucle.
     */
    protected function resyncFromSaga(Item $item, array $p, string $name): void
    {
        if (!$this->resyncContent) {
            return;
        }

        $variation = trim((string) data_get($p, 'Variation', ''));
        $descripcion = (string) data_get($p, 'Description', '');

        $item->description = mb_substr($name, 0, 600);
        $item->name = mb_substr(trim($name . ($variation ? " {$variation}" : '')), 0, 600);

        if ($descripcion !== '') {
            $item->mp_notes = mb_substr($descripcion, 0, 5000);
        }

        if ($brand = $this->resolveBrand((string) data_get($p, 'Brand', ''))) {
            $item->brand_id = $brand->id;
        }
        if ($category = $this->resolveCategory((string) data_get($p, 'PrimaryCategory', ''))) {
            $item->category_id = $category->id;
        }

        $item->save();
    }

    /** Registra un aviso: el producto entró, pero con una salvedad visible. */
    protected function warn(string $sku, string $name, string $message): void
    {
        $this->warnings[] = ['sku' => $sku, 'name' => $name, 'error' => $message];
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

    protected function createItem(array $p, string $sku, string $name, float $price, ?float $compareAt, ?string $from, ?string $until, int $stock, string $status = ''): Item
    {
        // Un producto que en Saga está inactivo / dado de baja / rechazado no
        // debe entrar publicado en la tienda del tenant.
        $publish = $this->isPublishable($status);
        if (!$publish) {
            $this->warn($sku, $name, "En Saga está en estado «{$status}»: se importó desactivado y fuera de la tienda.");
        }

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
        $item->active = $publish;
        $item->image = 'imagen-no-disponible.jpg';
        $item->image_medium = 'imagen-no-disponible.jpg';
        $item->image_small = 'imagen-no-disponible.jpg';
        $item->apply_store = $publish;               // visible en la tienda del tenant
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

    /**
     * Resuelve la categoría del ERP para lo que Saga manda en PrimaryCategory.
     *
     * El tenant ya homologa sus categorías con las de Saga en «Categorías Saga»
     * (tabla saga_category_map). La importación la ignoraba y creaba una
     * categoría nueva con el texto de Saga, llenando el ERP de categorías
     * paralelas a las que el usuario ya había mapeado a mano.
     *
     * Saga puede mandar ahí un id, un nombre o una ruta, así que se busca contra
     * las tres columnas homologadas (y contra la última hoja de la ruta).
     */
    protected function resolveCategory(string $name): ?Category
    {
        $name = trim($name) ?: 'General';

        $mappedId = $this->categoryMap()[$this->normalizeKey($name)] ?? null;
        if ($mappedId && ($mapped = Category::find($mappedId))) {
            return $mapped;
        }

        $clean = Category::normalizeName($name);

        $cat = Category::whereRaw('LOWER(name) IN (?, ?)', [
            mb_strtolower($name),
            mb_strtolower($clean),
        ])->first();

        if ($cat) {
            return $cat;
        }

        // Lo que Saga manda sin homologar NO puede seguir aterrizando en la
        // raíz del catálogo: así es como carolayimport acabó con 164
        // categorías de primer nivel y la tienda con una barra impasable.
        // Nace colgando de «Sin clasificar» y oculta en el escaparate; el
        // comerciante la coloca donde toque desde el panel de Categorías.
        return Category::create([
            'name'              => $clean,
            'parent_id'         => $this->unclassifiedCategory()->id,
            'visible_ecommerce' => false,
        ]);
    }

    /**
     * El cajón de las categorías nuevas sin clasificar. Se crea la primera vez
     * que hace falta y se reutiliza siempre.
     */
    protected function unclassifiedCategory(): Category
    {
        static $cached = null;

        if ($cached && Category::whereKey($cached->id)->exists()) {
            return $cached;
        }

        $cached = Category::whereNull('parent_id')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(Category::UNCLASSIFIED)])
            ->first();

        return $cached ?: ($cached = Category::create([
            'name'              => Category::UNCLASSIFIED,
            'visible_ecommerce' => false,
        ]));
    }

    /**
     * Índice [clave homologada → category_id] del canal, cargado una sola vez.
     * Cada fila aporta varias claves porque no sabemos cuál de las tres manda
     * Saga en PrimaryCategory.
     */
    protected function categoryMap(): array
    {
        if ($this->categoryMap !== null) {
            return $this->categoryMap;
        }

        $this->categoryMap = [];

        try {
            $maps = SagaCategoryMap::where('channel_id', $this->channel->id)->get();
        } catch (\Throwable $e) {
            return $this->categoryMap; // tenant sin la tabla de homologación
        }

        foreach ($maps as $m) {
            $path = (string) $m->saga_category_path;
            $leaf = $path !== '' ? trim((string) last(preg_split('/\s*[>\/]\s*/', $path))) : '';

            foreach ([$m->saga_category_id, $m->saga_category_name, $path, $leaf] as $clave) {
                $clave = $this->normalizeKey((string) $clave);
                if ($clave !== '') {
                    $this->categoryMap[$clave] = $m->category_id;
                }
            }
        }

        return $this->categoryMap;
    }

    protected function normalizeKey(string $v): string
    {
        return mb_strtolower(trim($v));
    }

    protected function resolveBrand(string $name): ?Brand
    {
        $name = trim($name) ?: 'Genérica';
        $brand = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();
        return $brand ?: Brand::create(['name' => $name]);
    }

    /**
     * Resuelve las imágenes del producto: las encola (modo HTTP) o las descarga
     * en el acto (modo CLI). El trabajo real vive en SagaImageImporter.
     */
    protected function handleImages(Item $item, array $p, string $name, bool $isNew): void
    {
        if (!$this->withImages) {
            return;
        }

        $urls = SagaImageImporter::extractUrls($p);
        if ($urls['main'] === '' && empty($urls['gallery'])) {
            return;
        }

        if ($this->deferImages) {
            ImportSagaProductImagesJob::dispatch($item->id, $urls['main'], $urls['gallery'], $name, $isNew);
            $this->imagesQueued++;
            return;
        }

        SagaImageImporter::apply($item, $urls['main'], $urls['gallery'], $name, $isNew);
    }
}
