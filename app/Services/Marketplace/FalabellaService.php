<?php

namespace App\Services\Marketplace;

use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\MarketplaceOrder;
use App\Models\Tenant\MarketplaceSyncLog;
use App\Models\Tenant\Item;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Integración con Falabella Seller Center API
 *
 * Endpoints base: https://sellercenter-api.falabella.com
 * Auth: API Key + User ID (firma HMAC)
 * Docs: https://sellercenter.falabella.com/docs/
 */
class FalabellaService
{
    protected MarketplaceChannel $channel;
    protected string $baseUrl;
    protected string $apiKey;
    protected string $userId;

    public function __construct(MarketplaceChannel $channel)
    {
        $this->channel = $channel;
        $this->baseUrl = $channel->getCredential('api_url', 'https://sellercenter-api.falabella.com');
        $this->apiKey = $channel->getCredential('api_key', '');
        $this->userId = $channel->getCredential('user_id', '');
    }

    // ══════════════════════════════════════════════════════════════
    // AUTHENTICATION — Firma HMAC-SHA256
    // ══════════════════════════════════════════════════════════════

    protected function signRequest(string $action, array $params = []): array
    {
        $params = array_merge($params, [
            'Action' => $action,
            'Format' => 'JSON',
            'Timestamp' => now()->toIso8601String(),
            'UserID' => $this->userId,
            'Version' => '1.0',
        ]);

        ksort($params);
        $queryString = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $params['Signature'] = rawurlencode(hash_hmac('sha256', $queryString, $this->apiKey));

        return $params;
    }

    protected function call(string $action, array $params = [])
    {
        $signed = $this->signRequest($action, $params);

        $response = Http::timeout(30)
            ->retry(3, 1000)
            ->get($this->baseUrl, $signed);

        return $this->handleResponse($action, $response);
    }

    /**
     * Variante POST para acciones de escritura de estado (SetStatusTo*).
     * Seller Center firma TODOS los params en el query string; el body va vacío.
     */
    protected function callPost(string $action, array $params = [])
    {
        $signed = $this->signRequest($action, $params);
        $url = $this->baseUrl . '?' . http_build_query($signed, '', '&', PHP_QUERY_RFC3986);

        $response = Http::timeout(30)->post($url);

        return $this->handleResponse($action, $response);
    }

    /**
     * Variante de POST para las acciones de FEED de catálogo (ProductCreate,
     * ProductUpdate, ProductStockUpdate, ProductPriceUpdate).
     *
     * En Seller Center estas acciones NO van como GET con el XML en la query:
     * el XML viaja en el BODY de un POST y SOLO los parámetros estándar
     * (Action/UserID/Timestamp/Version/Format) se firman en el query string. La
     * firma NO incluye el cuerpo XML. Enviarlo por GET (como hacía call()) trunca
     * el payload y Falabella lo rechaza → por eso el push de productos/stock/precio
     * nunca funcionó.
     */
    protected function callFeed(string $action, string $xmlBody)
    {
        $signed = $this->signRequest($action);
        $url = $this->baseUrl . '?' . http_build_query($signed, '', '&', PHP_QUERY_RFC3986);

        $response = Http::withBody($xmlBody, 'text/xml')
            ->timeout(60)
            ->retry(2, 1000)
            ->post($url);

        return $this->handleResponse($action, $response);
    }

    protected function handleResponse(string $action, $response)
    {
        if ($response->failed()) {
            $error = $response->json('ErrorResponse.Head.ErrorMessage', $response->body());
            Log::channel('payments')->error("Falabella API error: {$action}", [
                'channel_id' => $this->channel->id,
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new \RuntimeException("Falabella API error [{$action}]: {$error}");
        }

        return $response->json('SuccessResponse.Body') ?? $response->json();
    }

    // ══════════════════════════════════════════════════════════════
    // CONNECTION TEST — Verificar credenciales realmente
    // ══════════════════════════════════════════════════════════════

    /**
     * Construir un servicio a partir de credenciales sueltas (sin canal persistido).
     * Útil para probar la conexión antes de guardar.
     */
    public static function fromCredentials(array $credentials): self
    {
        $channel = new MarketplaceChannel([
            'platform'    => 'falabella',
            'credentials' => $credentials,
        ]);

        return new self($channel);
    }

    /**
     * Probar la conexión haciendo una llamada firmada real a Falabella.
     * A diferencia del check anterior (status < 500), esto valida la firma:
     * sólo retorna success=true si la API responde SuccessResponse.
     */
    public function testConnection(): array
    {
        if ($this->apiKey === '' || $this->userId === '') {
            return ['success' => false, 'message' => 'Falta User ID o API Key'];
        }

        try {
            $signed = $this->signRequest('GetProducts', ['Limit' => 1]);

            $response = Http::timeout(15)->get($this->baseUrl, $signed);
            $json = $response->json() ?? [];

            if (isset($json['SuccessResponse'])) {
                return ['success' => true, 'message' => 'Conexión exitosa con Seller Center'];
            }

            $message = data_get($json, 'ErrorResponse.Head.ErrorMessage')
                ?: data_get($json, 'ErrorResponse.Head.ErrorCode')
                ?: 'Credenciales inválidas (firma rechazada por Falabella)';

            return ['success' => false, 'message' => $message];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'No se pudo contactar a Falabella: ' . $e->getMessage()];
        }
    }

    // ══════════════════════════════════════════════════════════════
    // CATALOG — Leer el catálogo del seller (Saga → EBAEMY)
    // ══════════════════════════════════════════════════════════════

    /**
     * Trae los productos del seller desde Saga (GetProducts).
     * Normaliza la respuesta a una lista plana de productos.
     */
    public function getProducts(array $params = []): array
    {
        $result = $this->call('GetProducts', $params);
        $prods = data_get($result, 'Products.Product', []);

        // La API devuelve un objeto suelto si hay un solo producto.
        if (isset($prods['SellerSku'])) {
            $prods = [$prods];
        }

        return is_array($prods) ? $prods : [];
    }

    // ══════════════════════════════════════════════════════════════
    // CATEGORY HOMOLOGATION — Árbol y atributos de Saga (Fase 1)
    // ══════════════════════════════════════════════════════════════

    /**
     * Trae el árbol de categorías de Saga (GetCategoryTree) y lo APLANA a una
     * lista con su ruta (breadcrumb). Marca cuáles son hoja: SOLO una categoría
     * hoja es asignable como PrimaryCategory de un producto.
     *
     * @return array<int, array{id:string, name:string, path:string, is_leaf:bool, depth:int}>
     */
    public function getCategoryTree(): array
    {
        $result = $this->call('GetCategoryTree');
        $roots = data_get($result, 'Categories.Category', []);
        if (isset($roots['CategoryId']) || isset($roots['Name'])) {
            $roots = [$roots]; // un solo nodo raíz
        }

        $flat = [];
        $this->flattenCategories(is_array($roots) ? $roots : [], '', 0, $flat);
        return $flat;
    }

    /**
     * Recorre recursivamente los nodos del árbol y los acumula aplanados.
     */
    protected function flattenCategories(array $nodes, string $parentPath, int $depth, array &$out): void
    {
        foreach ($nodes as $node) {
            if (!is_array($node)) continue;

            $id = (string) ($node['CategoryId'] ?? '');
            $name = (string) ($node['Name'] ?? '');
            if ($id === '' && $name === '') continue;

            $path = $parentPath === '' ? $name : ($parentPath . ' › ' . $name);

            $children = data_get($node, 'Children.Category', []);
            if (isset($children['CategoryId']) || isset($children['Name'])) {
                $children = [$children];
            }
            $children = is_array($children) ? $children : [];
            $isLeaf = count($children) === 0;

            $out[] = [
                'id' => $id,
                'name' => $name,
                'path' => $path,
                'is_leaf' => $isLeaf,
                'depth' => $depth,
            ];

            if (!$isLeaf) {
                $this->flattenCategories($children, $path, $depth + 1, $out);
            }
        }
    }

    /**
     * Trae los atributos que Saga exige para una categoría (GetCategoryAttributes)
     * y los normaliza. Los marcados mandatory=true son obligatorios para publicar.
     *
     * @return array<int, array{name:string, label:string, mandatory:bool, input_type:string, type:string, options:array}>
     */
    public function getCategoryAttributes(string $primaryCategory): array
    {
        $result = $this->call('GetCategoryAttributes', ['PrimaryCategory' => $primaryCategory]);
        $attrs = data_get($result, 'Attributes.Attribute', []);
        if (isset($attrs['Name']) || isset($attrs['Label'])) {
            $attrs = [$attrs];
        }
        if (!is_array($attrs)) {
            return [];
        }

        return collect($attrs)->map(function ($a) {
            $options = data_get($a, 'Options.Option', []);
            if (isset($options['Name']) || isset($options['GlobalIdentifier'])) {
                $options = [$options];
            }
            $options = collect(is_array($options) ? $options : [])
                ->map(fn ($o) => is_array($o) ? ($o['Name'] ?? $o['GlobalIdentifier'] ?? null) : $o)
                ->filter()
                ->values()
                ->all();

            return [
                'name' => (string) ($a['Name'] ?? ''),
                'label' => (string) ($a['Label'] ?? $a['Name'] ?? ''),
                // Seller Center devuelve "0"/"1" como string en isMandatory.
                'mandatory' => (bool) ((int) ($a['isMandatory'] ?? 0)),
                'input_type' => (string) ($a['InputType'] ?? 'text'),
                'type' => (string) ($a['AttributeType'] ?? ''),
                'options' => $options,
            ];
        })->filter(fn ($a) => $a['name'] !== '')->values()->all();
    }

    /**
     * Trae las marcas registradas del seller en Saga (GetBrands). Útil para la
     * homologación de marcas (Saga valida la marca contra su catálogo).
     *
     * @return array<int, array{id:string, name:string}>
     */
    public function getBrands(): array
    {
        $result = $this->call('GetBrands');
        $brands = data_get($result, 'Brands.Brand', []);
        if (isset($brands['Name'])) {
            $brands = [$brands];
        }
        if (!is_array($brands)) {
            return [];
        }

        return collect($brands)->map(fn ($b) => [
            'id' => (string) ($b['BrandId'] ?? $b['GlobalIdentifier'] ?? ''),
            'name' => (string) ($b['Name'] ?? ''),
        ])->filter(fn ($b) => $b['name'] !== '')->values()->all();
    }

    // ══════════════════════════════════════════════════════════════
    // PRODUCTS — Crear / Actualizar productos
    // ══════════════════════════════════════════════════════════════

    /**
     * Sincronizar productos del ERP → Falabella
     */
    public function syncProducts(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'sync_products', 'push', function ($log) {
            $mappings = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('sync_status', 'pending')
                ->with(['item', 'variant'])
                ->limit(50) // Batch de 50 (límite API)
                ->get();

            $success = 0;
            $failed = 0;
            $errors = [];

            foreach ($mappings as $mapping) {
                try {
                    $this->pushProduct($mapping);
                    // pushProduct deja el estado correcto: 'synced' si fue UPDATE de
                    // un producto existente, o 'pending' + qc_status='queued' si fue
                    // un CREATE (el feed sigue en cola/QC → lo resuelve
                    // syncFeedStatuses). Aquí solo limpiamos el último error.
                    $mapping->update(['last_error' => null]);
                    $success++;
                } catch (\Throwable $e) {
                    $mapping->update(['sync_status' => 'error', 'last_error' => $e->getMessage()]);
                    $errors[] = ['sku' => $mapping->external_sku, 'error' => $e->getMessage()];
                    $failed++;
                }
            }

            return [
                'processed' => $mappings->count(),
                'success' => $success,
                'failed' => $failed,
                'details' => $errors ?: null,
            ];
        })->toArray();
    }

    /**
     * Publica/actualiza UN producto y deja el resultado en el mapeo (sync_status /
     * last_error). Lo usa PublishProductToSagaJob (auto-publish). No lanza: devuelve
     * el resultado para que el job decida.
     */
    public function publishOne(MarketplaceProduct $mapping): array
    {
        try {
            $this->pushProduct($mapping);
            $mapping->update(['last_error' => null]);
            return ['success' => true];
        } catch (\Throwable $e) {
            $mapping->update(['sync_status' => 'error', 'last_error' => $e->getMessage()]);
            Log::channel('payments')->error('Falabella publishOne failed', [
                'channel_id' => $this->channel->id,
                'sku'        => $mapping->external_sku,
                'error'      => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Activa o desactiva el listing en Saga (delist REVERSIBLE: no borra el
     * producto, solo lo saca/devuelve a la vitrina). Best-effort: si el producto
     * aún no existe en Saga (sin external_id) o la API falla, NO lanza —
     * degrada al comportamiento previo (el caller ya marcó el estado interno).
     *
     * NOTA: la etiqueta <Status> de ProductUpdate sigue la forma documentada de
     * Seller Center pero se valida en vivo en el 1er uso real contra la cuenta.
     */
    public function setProductActive(MarketplaceProduct $mapping, bool $active): array
    {
        if (!$mapping->external_id) {
            // Aún no publicado en Saga (o en cola de QC) → nada que (des)activar.
            return ['success' => false, 'skipped' => true];
        }

        $status = $active ? 'active' : 'inactive';
        try {
            $builder = new SagaProductPayloadBuilder($this->channel, $mapping);
            $this->callFeed('ProductUpdate', $builder->statusXml($status));
            return ['success' => true, 'status' => $status];
        } catch (\Throwable $e) {
            Log::channel('payments')->warning('Falabella setProductActive falló', [
                'channel_id' => $this->channel->id,
                'sku'        => $mapping->external_sku,
                'status'     => $status,
                'error'      => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    protected function pushProduct(MarketplaceProduct $mapping): void
    {
        if (!$mapping->item) {
            throw new \RuntimeException("Item not found: {$mapping->item_id}");
        }

        // Builder + validación previa (Fase 2): no enviamos a Saga un producto
        // incompleto; el motivo exacto queda en el error → last_error del mapeo.
        $builder = new SagaProductPayloadBuilder($this->channel, $mapping);
        $problems = $builder->validate();
        if (!empty($problems)) {
            throw new \RuntimeException('Producto incompleto para Saga: ' . implode(' ', $problems));
        }

        if ($mapping->external_id) {
            // Update de un producto YA existente en Saga (POST, XML en el body).
            // SIN precio: el seller maneja precio/ofertas en el panel de Saga (el
            // cron de precios está OFF a propósito); reenviarlo lo pisaría. Sí
            // actualizamos datos del producto + stock.
            $this->callFeed('ProductUpdate', $builder->toXml(false));
            $mapping->update(['sync_status' => 'synced', 'qc_status' => 'live', 'synced_at' => now()]);
        } else {
            // Create ASÍNCRONO: Seller Center devuelve un FeedId y el producto pasa
            // por QC; el ProductId aprobado y el estado se obtienen luego vía
            // FeedStatus/GetProducts (syncFeedStatuses). NO marcamos synced ni
            // fijamos un external_id falso → queda 'pending' + qc_status='queued'.
            // CON precio: Saga necesita el precio inicial al crear.
            $result = $this->callFeed('ProductCreate', $builder->toXml(true));
            $feedId = data_get($result, 'FeedId') ?? data_get($result, 'RequestId');
            $mapping->update([
                'feed_id'     => $feedId,
                'qc_status'   => 'queued',
                'sync_status' => 'pending',
                'external_data' => array_merge(
                    is_array($mapping->external_data) ? $mapping->external_data : [],
                    ['feed_submitted_at' => now()->toIso8601String()]
                ),
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════════
    // FEED STATUS — Resolver el ProductCreate asíncrono / QC (Fase 3)
    // ══════════════════════════════════════════════════════════════

    /**
     * Resuelve el estado de los productos enviados con ProductCreate (asíncrono).
     * Por cada mapeo con feed_id no terminal consulta FeedStatus:
     *  - sigue en cola/proceso → actualiza qc_status y espera.
     *  - terminó con errores → qc_status='rejected' + rejection_reasons + last_error.
     *  - terminó OK → busca el ProductId asignado y el estado real (refreshProductStatus).
     */
    public function syncFeedStatuses(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'feed_status', 'pull', function ($log) {
            $mappings = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->whereNotNull('feed_id')
                ->where(function ($q) {
                    $q->whereNull('qc_status')
                      ->orWhereIn('qc_status', ['queued', 'processing']);
                })
                ->limit(100)
                ->get();

            $processed = 0;
            $resolved = 0;
            $errors = [];

            foreach ($mappings as $m) {
                try {
                    $processed++;
                    $detail = $this->call('FeedStatus', ['FeedID' => $m->feed_id]);
                    $status = strtolower((string) (
                        data_get($detail, 'FeedDetail.Status')
                        ?? data_get($detail, 'Status')
                        ?? ''
                    ));

                    // Aún en cola/proceso → guardamos el avance y seguimos esperando.
                    if (!in_array($status, ['finished', 'canceled', 'cancelled'], true)) {
                        $m->update(['qc_status' => $status ?: 'processing']);
                        continue;
                    }

                    // Errores del feed para este SKU.
                    $feedErrors = data_get($detail, 'FeedDetail.FeedErrors.Error',
                        data_get($detail, 'FeedErrors.Error', []));
                    if (isset($feedErrors['Message']) || isset($feedErrors['Code'])) {
                        $feedErrors = [$feedErrors];
                    }
                    $feedErrors = is_array($feedErrors) ? $feedErrors : [];

                    if (!empty($feedErrors)) {
                        $msgs = array_map(
                            fn ($e) => trim((string) (($e['Code'] ?? '') . ' ' . ($e['Message'] ?? ''))),
                            $feedErrors
                        );
                        $m->update([
                            'qc_status'         => 'rejected',
                            'rejection_reasons' => $feedErrors,
                            'sync_status'       => 'error',
                            'last_error'        => implode(' | ', array_filter($msgs)) ?: 'Rechazado por Saga',
                        ]);
                        $resolved++;
                        continue;
                    }

                    // Feed aceptado → traer el ProductId y el estado real del producto.
                    $this->refreshProductStatus($m);
                    $resolved++;
                } catch (\Throwable $e) {
                    $errors[] = ['feed' => $m->feed_id, 'sku' => $m->external_sku, 'error' => $e->getMessage()];
                }
            }

            return ['processed' => $processed, 'success' => $resolved, 'failed' => count($errors), 'details' => $errors ?: null];
        })->toArray();
    }

    /**
     * Busca en Saga el producto por su SellerSku (GetProducts) y sincroniza el
     * external_id (ProductId/ShopSku) y el estado QC en el mapeo local.
     */
    public function refreshProductStatus(MarketplaceProduct $m): void
    {
        $sku = (string) $m->external_sku;
        if ($sku === '') {
            return;
        }

        $prods = $this->getProducts(['Search' => $sku, 'Limit' => 20]);
        $match = collect($prods)->first(function ($p) use ($sku) {
            return (string) data_get($p, 'SellerSku') === $sku
                || (string) data_get($p, 'ShopSku') === $sku;
        });

        // Creado pero aún no indexado / en QC: lo dejamos en 'processing' para
        // reintentar en la próxima pasada.
        if (!$match) {
            $m->update(['qc_status' => 'processing']);
            return;
        }

        $externalId = data_get($match, 'ProductId')
            ?: data_get($match, 'ShopSku')
            ?: data_get($match, 'SellerSku');

        $statusRaw = strtolower((string) (
            data_get($match, 'Status')
            ?? data_get($match, 'BusinessUnits.BusinessUnit.Status')
            ?? ''
        ));

        $qc = match (true) {
            str_contains($statusRaw, 'reject') || str_contains($statusRaw, 'disapprov') => 'rejected',
            str_contains($statusRaw, 'active') => 'live',
            str_contains($statusRaw, 'pending') || str_contains($statusRaw, 'qc')       => 'processing',
            $statusRaw !== '' => $statusRaw,
            default => 'approved',
        };

        $m->update([
            'external_id' => $externalId,
            'qc_status'   => $qc,
            'sync_status' => $qc === 'rejected' ? 'error' : 'synced',
            'synced_at'   => now(),
            'last_error'  => $qc === 'rejected' ? ($m->last_error ?: 'Rechazado en QC de Saga') : null,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    // STOCK — Actualizar stock en batch
    // ══════════════════════════════════════════════════════════════

    /**
     * Sincronizar stock de todos los productos mapeados
     */
    public function syncStock(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'sync_stock', 'push', function ($log) {
            // Sincroniza por SellerSku: aplica tanto a productos creados desde EBAEMY
            // como a los que el seller ya tenía en Seller Center (mapeados, status 'pending').
            $mappings = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('sync_status', '!=', 'excluded')
                ->whereNotNull('external_sku')
                ->where('external_sku', '!=', '')
                ->with(['item.warehouses', 'item.variants.warehouseStocks', 'variant'])
                ->get();

            $skus = [];
            foreach ($mappings as $mapping) {
                if ($mapping->variant) {
                    // Mapeo de UNA variante concreta.
                    $skus[] = ['SellerSku' => $mapping->external_sku, 'Quantity' => max(0, (int) $mapping->variant->stock)];
                } elseif ($mapping->item && $mapping->item->has_variants && $mapping->item->variants->count() > 0) {
                    // Producto con variantes: se publicó un <Sku> por variante →
                    // empujamos stock por variante con el MISMO SellerSku que el
                    // builder generó (variant->sku o internal_id-V{id}).
                    $fallback = $mapping->item->internal_id ?: $mapping->item->item_code;
                    foreach ($mapping->item->variants as $v) {
                        $vsku = $v->sku ?: ($fallback . '-V' . $v->id);
                        $vqty = isset($v->warehouseStocks)
                            ? (int) $v->warehouseStocks->sum('stock')
                            : (int) ($v->stock ?? 0);
                        $skus[] = ['SellerSku' => $vsku, 'Quantity' => max(0, $vqty)];
                    }
                } else {
                    // Producto simple: SUMA item_warehouse.stock (no items.stock).
                    $stock = max(0, (int) ($mapping->item ? $mapping->item->warehouses->sum('stock') : 0));
                    $skus[] = ['SellerSku' => $mapping->external_sku, 'Quantity' => $stock];
                }
            }

            // Batch update (Falabella acepta hasta 100 por request)
            $chunks = array_chunk($skus, 100);
            $success = 0;
            $failed = 0;
            $errors = [];

            foreach ($chunks as $i => $chunk) {
                try {
                    $xml = '<?xml version="1.0" encoding="UTF-8"?><Request>';
                    foreach ($chunk as $sku) {
                        $skuXml = htmlspecialchars($sku['SellerSku']);
                        $xml .= "<Product><SellerSku>{$skuXml}</SellerSku>"
                              . "<Quantity>{$sku['Quantity']}</Quantity></Product>";
                    }
                    $xml .= '</Request>';

                    $this->callFeed('ProductStockUpdate', $xml);
                    $success += count($chunk);
                } catch (\Throwable $e) {
                    $failed += count($chunk);
                    $errors[] = ['batch' => $i, 'count' => count($chunk), 'error' => $e->getMessage()];
                    Log::channel('payments')->error('Falabella ProductStockUpdate batch failed', [
                        'channel_id' => $this->channel->id,
                        'batch' => $i,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ['processed' => count($skus), 'success' => $success, 'failed' => $failed, 'details' => $errors ?: null];
        })->toArray();
    }

    // ══════════════════════════════════════════════════════════════
    // ORDERS — Obtener y procesar pedidos
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtener órdenes pendientes de Falabella
     */
    public function fetchOrders(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'fetch_orders', 'pull', function ($log) {
            $result = $this->call('GetOrders', [
                'Status' => 'pending',
                'SortBy' => 'created_at',
                'SortDirection' => 'DESC',
                'Limit' => 100,
            ]);

            $orders = $result['Orders']['Order'] ?? [];
            if (!is_array($orders)) $orders = [$orders];

            $created = 0;
            $errors = [];

            foreach ($orders as $orderData) {
                try {
                    $externalId = $orderData['OrderId'] ?? null;
                    if (!$externalId) continue;

                    // Evitar duplicados
                    $exists = MarketplaceOrder::where('channel_id', $this->channel->id)
                        ->where('external_order_id', $externalId)
                        ->exists();
                    if ($exists) continue;

                    // Obtener items de la orden
                    $itemsResult = $this->call('GetOrderItems', ['OrderId' => $externalId]);
                    $orderItems = $itemsResult['OrderItems']['OrderItem'] ?? [];

                    // Normalizar: GetOrderItems devuelve objeto suelto si hay 1 item
                    if (isset($orderItems['OrderItemId'])) {
                        $orderItems = [$orderItems];
                    }

                    $mpOrder = MarketplaceOrder::create([
                        'channel_id' => $this->channel->id,
                        'external_order_id' => $externalId,
                        'status' => 'pending',
                        'customer_data' => $this->buildCustomerData($orderData),
                        'items_data' => $orderItems,
                        'shipping_data' => [
                            'address' => $orderData['ShippingAddress'] ?? null,
                            'city' => $orderData['ShippingCity'] ?? null,
                            'method' => $orderData['ShippingType'] ?? null,
                            // Límite de despacho de Saga ("Promised shipping time")
                            'promised_shipping_time' => $orderData['PromisedShippingTime'] ?? null,
                        ],
                        'total' => (float) ($orderData['Price'] ?? 0),
                        'ordered_at' => $orderData['CreatedAt'] ?? now(),
                    ]);

                    // Descontar stock en ERP
                    $this->processOrderStock($orderItems);

                    // Avisar al vendedor (email) que entró un pedido nuevo
                    $this->notifyNewOrder($mpOrder);

                    // Convertir automáticamente a Order interno para que aparezca
                    // en la página Pedidos (no toca el status de despacho). Defensivo:
                    // no rompe el fetch si la conversión falla.
                    try {
                        $mpOrder->createErpOrder();
                    } catch (\Throwable $e) {
                        Log::channel('payments')->warning("No se pudo auto-convertir pedido Saga #{$externalId} a Order: {$e->getMessage()}");
                    }

                    $created++;
                } catch (\Throwable $e) {
                    $errors[] = ['order' => $externalId ?? 'unknown', 'error' => $e->getMessage()];
                }
            }

            return ['processed' => count($orders), 'success' => $created, 'failed' => count($errors), 'details' => $errors ?: null];
        })->toArray();
    }

    /**
     * Descontar stock cuando se recibe una orden de Falabella
     */
    protected function processOrderStock(array $items): void
    {
        foreach ($items as $orderItem) {
            $sku = $orderItem['ShopSku'] ?? $orderItem['Sku'] ?? null;
            if (!$sku) continue;

            $mapping = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('external_sku', $sku)
                ->first();

            if (!$mapping) continue;

            $qty = (int) ($orderItem['Quantity'] ?? 1);

            DB::connection('tenant')->transaction(function () use ($mapping, $qty) {
                if ($mapping->item_variant_id) {
                    $vw = \App\Models\Tenant\ItemVariantWarehouse::where('item_variant_id', $mapping->item_variant_id)
                        ->lockForUpdate()->first();
                    if ($vw) {
                        // 1. Descontar de la hoja (fuente de verdad).
                        $vw->stock_physical = max(0, $vw->stock_physical - $qty);
                        $vw->stock = $vw->stock_physical;
                        $vw->save();

                        // 2 + 3. Recalcular los agregados derivados. Sin esto, el
                        // tenant (items.stock) y el marketplace (item_variants.stock)
                        // quedan desincronizados y se puede sobrevender.
                        // Ver skill ebaemy-stock-flow (regla de oro).
                        $variant = $vw->variant;
                        if ($variant) {
                            $variant->stock = \App\Models\Tenant\ItemVariantWarehouse::where('item_variant_id', $variant->id)
                                ->sum('stock_physical');
                            $variant->save();
                            app(\App\Services\Tenant\ItemVariantService::class)
                                ->propagateStock($variant->item);
                        }
                    }
                } else {
                    $iw = \App\Models\Tenant\ItemWarehouse::where('item_id', $mapping->item_id)
                        ->lockForUpdate()->first();
                    if ($iw) {
                        $iw->applyStockMovement(
                            \App\Enums\StockMovementTypeEnum::SALE_STORE,
                            $qty
                        );
                    }
                }
            });
        }
    }

    /**
     * RESTITUYE el stock de un pedido cancelado/devuelto (reverso de
     * processOrderStock): devuelve las unidades a stock_physical. Idempotente
     * por marketplace_orders.stock_restored_at. Sigue el flujo correcto del
     * skill ebaemy-stock-flow (variante → hoja + propagación).
     */
    public function restoreOrderStock(MarketplaceOrder $mp): bool
    {
        if ($mp->stock_restored_at) {
            return false; // ya restituido
        }

        $items = is_array($mp->items_data) ? $mp->items_data : [];
        if (isset($items['OrderItemId'])) {
            $items = [$items];
        }

        foreach ($items as $orderItem) {
            if (!is_array($orderItem)) continue;
            $sku = $orderItem['ShopSku'] ?? $orderItem['Sku'] ?? null;
            if (!$sku) continue;

            $mapping = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('external_sku', $sku)
                ->first();
            if (!$mapping) continue;

            $qty = (int) ($orderItem['Quantity'] ?? 1) ?: 1;

            DB::connection('tenant')->transaction(function () use ($mapping, $qty) {
                if ($mapping->item_variant_id) {
                    $vw = \App\Models\Tenant\ItemVariantWarehouse::where('item_variant_id', $mapping->item_variant_id)
                        ->lockForUpdate()->first();
                    if ($vw) {
                        $vw->stock_physical = $vw->stock_physical + $qty; // devuelve
                        $vw->stock = $vw->stock_physical;
                        $vw->save();

                        $variant = $vw->variant;
                        if ($variant) {
                            $variant->stock = \App\Models\Tenant\ItemVariantWarehouse::where('item_variant_id', $variant->id)
                                ->sum('stock_physical');
                            $variant->save();
                            app(\App\Services\Tenant\ItemVariantService::class)
                                ->propagateStock($variant->item);
                        }
                    }
                } else {
                    $iw = \App\Models\Tenant\ItemWarehouse::where('item_id', $mapping->item_id)
                        ->lockForUpdate()->first();
                    if ($iw) {
                        $iw->applyStockMovement(
                            \App\Enums\StockMovementTypeEnum::SALE_STORE_RETURN,
                            $qty
                        );
                    }
                }
            });
        }

        $mp->stock_restored_at = now();
        $mp->save();

        Log::channel('payments')->info('Saga: stock restituido por cancelación/devolución', [
            'marketplace_order_id' => $mp->id,
            'external_order_id'    => $mp->external_order_id,
        ]);

        return true;
    }

    // ══════════════════════════════════════════════════════════════
    // FULFILLMENT — Despacho de pedidos (vuelta a Saga)
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtiene los items de una orden de Saga (OrderItemId, estado, tracking...).
     */
    public function getOrderItems(string $externalOrderId): array
    {
        $result = $this->call('GetOrderItems', ['OrderId' => $externalOrderId]);
        $items = data_get($result, 'OrderItems.OrderItem', []);
        if (isset($items['OrderItemId'])) {
            $items = [$items];
        }
        return is_array($items) ? $items : [];
    }

    /**
     * Reconcilia el estado de los pedidos locales NO terminales contra Saga.
     *
     * fetchOrders solo CREA pedidos nuevos (filtra Status=pending) y nunca toca
     * los que ya tenía → una vez fetchado, si en Saga se despacha/cancela/entrega,
     * el estado local quedaba 'pending' para siempre. Esto consulta cada pedido
     * local pending/ready_to_ship y actualiza su estado real (y el límite de
     * despacho) desde los items de Saga.
     */
    public function reconcileOrders(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'reconcile_orders', 'pull', function ($log) {
            // Pendientes/listos (progresión de estado) + enviados/entregados de los
            // últimos 35 días (ventana de devolución de Saga) para detectar
            // cancelaciones/devoluciones y restituir el stock. Los ya cancelados
            // se excluyen.
            $locals = MarketplaceOrder::where('channel_id', $this->channel->id)
                ->where('status', '!=', 'canceled')
                ->where(function ($q) {
                    $q->whereIn('status', ['pending', 'ready_to_ship'])
                      ->orWhere('ordered_at', '>=', now()->subDays(35));
                })
                ->get();

            $checked = 0;
            $updated = 0;
            $errors = [];

            foreach ($locals as $mp) {
                try {
                    $items = $this->getOrderItems($mp->external_order_id);
                    $checked++;

                    $newStatus = $this->deriveStatusFromItems($items);
                    $dirty = false;

                    if ($newStatus && $newStatus !== $mp->status) {
                        $mp->status = $newStatus;
                        $dirty = true;
                    }

                    // Captura/actualiza el límite de despacho si Saga lo expone.
                    $deadline = null;
                    foreach ($items as $it) {
                        $deadline = $it['PromisedShippingTime'] ?? $deadline;
                    }
                    if ($deadline) {
                        $sd = is_array($mp->shipping_data) ? $mp->shipping_data : [];
                        if (($sd['promised_shipping_time'] ?? null) !== $deadline) {
                            $sd['promised_shipping_time'] = $deadline;
                            $mp->shipping_data = $sd;
                            $dirty = true;
                        }
                    }

                    if ($dirty) {
                        $mp->save();
                        // Mantiene /orders en sincronía con el estado real de Saga.
                        $mp->syncErpOrderStatus();
                        $updated++;
                    }

                    // Cancelado/devuelto → restituir stock (idempotente) y, si ya
                    // tenía boleta, marcar que falta emitir Nota de Crédito.
                    if ($newStatus === 'canceled') {
                        if ($this->restoreOrderStock($mp) && $mp->document_id && empty($mp->invoice_upload_error)) {
                            $mp->invoice_upload_error = 'DEVOLUCIÓN/CANCELACIÓN: emitir Nota de Crédito de la boleta de este pedido.';
                            $mp->save();
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = ['order' => $mp->external_order_id, 'error' => $e->getMessage()];
                }
            }

            return ['processed' => $checked, 'success' => $updated, 'failed' => count($errors), 'details' => $errors ?: null];
        })->toArray();
    }

    /**
     * Trae de Saga los pedidos DEVUELTOS y CANCELADOS (GetOrders por estado) y,
     * para los que tenemos localmente, marca Cancelado + restituye el stock +
     * avisa Nota de Crédito si ya tenían boleta.
     *
     * Saga NO tiene API de devoluciones aparte: las devoluciones se reflejan como
     * estado de orden 'returned' (además de 'canceled'). Esto es más eficiente que
     * escanear getOrderItems pedido por pedido.
     */
    public function reconcileReturns(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'reconcile_returns', 'pull', function ($log) {
            $processed = 0;
            $marked = 0;
            $errors = [];

            foreach (['returned', 'canceled'] as $sagaStatus) {
                try {
                    $result = $this->call('GetOrders', [
                        'Status' => $sagaStatus,
                        'Limit'  => 200,
                    ]);
                    $orders = data_get($result, 'Orders.Order', []);
                    if (isset($orders['OrderId'])) {
                        $orders = [$orders];
                    }
                    if (!is_array($orders)) {
                        continue;
                    }

                    foreach ($orders as $o) {
                        $oid = $o['OrderId'] ?? null;
                        if (!$oid) continue;
                        $processed++;

                        $mp = MarketplaceOrder::where('channel_id', $this->channel->id)
                            ->where('external_order_id', $oid)
                            ->first();
                        if (!$mp) continue; // no lo importamos → no descontamos su stock

                        if ($mp->status !== 'canceled') {
                            $mp->status = 'canceled';
                            $mp->save();
                            $mp->syncErpOrderStatus();
                        }

                        if ($this->restoreOrderStock($mp)) {
                            $marked++;
                            if ($mp->document_id && empty($mp->invoice_upload_error)) {
                                $mp->invoice_upload_error = 'DEVOLUCIÓN/CANCELACIÓN: emitir Nota de Crédito de la boleta de este pedido.';
                                $mp->save();
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = ['status' => $sagaStatus, 'error' => $e->getMessage()];
                }
            }

            return ['processed' => $processed, 'success' => $marked, 'failed' => count($errors), 'details' => $errors ?: null];
        })->toArray();
    }

    /**
     * Deriva el estado local de un pedido a partir del Status de sus items de Saga.
     * Toma el estado MENOS avanzado (si un item sigue pendiente, el pedido lo está).
     */
    protected function deriveStatusFromItems(array $items): ?string
    {
        // Mapa Saga → estado local. Orden = de menos a más avanzado.
        $rank = [
            'pending'        => 0,
            'ready_to_ship'  => 1,
            'shipped'        => 2,
            'delivered'      => 3,
            'canceled'       => 4,
            'cancelled'      => 4,
            'failed'         => 4,
        ];

        $statuses = [];
        foreach ($items as $it) {
            $s = strtolower(trim($it['Status'] ?? ''));
            if ($s === '') continue;
            // Cualquier variante de cancelación/fallo/devolución es terminal.
            if (str_contains($s, 'cancel') || str_contains($s, 'fail') || str_contains($s, 'return') || str_contains($s, 'reject')) {
                $s = 'canceled';
            }
            $statuses[] = $s;
        }
        if (empty($statuses)) {
            return null;
        }

        // Si TODOS están cancelados/fallidos → canceled.
        $allCanceled = collect($statuses)->every(fn($s) => ($rank[$s] ?? -1) >= 4);
        if ($allCanceled) {
            return 'canceled';
        }

        // Ignora cancelados parciales y toma el item activo MENOS avanzado.
        $active = array_filter($statuses, fn($s) => ($rank[$s] ?? 0) < 4);
        $least = collect($active)->sortBy(fn($s) => $rank[$s] ?? 0)->first();

        return match ($least) {
            'pending'       => 'pending',
            'ready_to_ship' => 'ready_to_ship',
            'shipped'       => 'shipped',
            'delivered'     => 'delivered',
            default         => null,
        };
    }

    /**
     * Marca items como LISTOS PARA DESPACHO (SetStatusToReadyToShip).
     * Para el modelo Dropshipping/Falabella, Saga ya asignó el tracking.
     */
    public function setReadyToShip(array $orderItemIds, string $deliveryType = 'dropship', string $shippingProvider = 'falabella', ?string $trackingNumber = null): array
    {
        $params = [
            'OrderItemIds' => '[' . implode(',', $orderItemIds) . ']',
            'DeliveryType' => $deliveryType,
            'ShippingProvider' => $shippingProvider,
        ];
        // En el modelo Dropshipping de Saga (ShippingProvider=falabella) la propia
        // Saga asigna el TrackingCode; reenviárselo es redundante y puede ser
        // rechazado. Solo mandamos tracking cuando el seller usa su propio courier.
        if ($trackingNumber && !$this->sagaManagesTracking($shippingProvider)) {
            $params['TrackingNumber'] = $trackingNumber;
        }
        return $this->callPost('SetStatusToReadyToShip', $params);
    }

    /**
     * ¿Saga asigna el tracking automáticamente? (modelo Falabella-managed /
     * clickandcollect). En ese caso NO debemos reenviar el TrackingNumber.
     */
    protected function sagaManagesTracking(string $shippingProvider): bool
    {
        return strtolower(trim($shippingProvider)) === 'falabella';
    }

    /**
     * Marca items como ENVIADOS (SetStatusToShipped).
     */
    public function setShipped(array $orderItemIds, string $deliveryType = 'dropship', string $shippingProvider = 'falabella', ?string $trackingNumber = null): array
    {
        $params = [
            'OrderItemIds' => '[' . implode(',', $orderItemIds) . ']',
            'DeliveryType' => $deliveryType,
            'ShippingProvider' => $shippingProvider,
        ];
        if ($trackingNumber && !$this->sagaManagesTracking($shippingProvider)) {
            $params['TrackingNumber'] = $trackingNumber;
        }
        return $this->callPost('SetStatusToShipped', $params);
    }

    /**
     * Descarga un documento de la orden (hoja de despacho, boleta/factura...).
     *
     * @param  string  $type  shippingLabel | invoice | carrierManifest
     * @return array  { mime, file (base64), filename }
     */
    public function getDocument(array $orderItemIds, string $type = 'shippingLabel'): array
    {
        $result = $this->call('GetDocument', [
            'OrderItemIds' => '[' . implode(',', $orderItemIds) . ']',
            'DocumentType' => $type,
        ]);

        return [
            'mime' => data_get($result, 'Documents.Document.MimeType', 'application/pdf'),
            'file' => data_get($result, 'Documents.Document.File'), // base64
            'type' => $type,
        ];
    }

    /**
     * Devuelve el número de comprobante que Saga ya tiene registrado para el
     * pedido (si lo hay), leyendo los items. Sirve para NO emitir una boleta
     * duplicada cuando el pedido ya fue facturado en otro sistema.
     * Tolerante con el nombre del campo (InvoiceNumber / InvoiceDocumentLink).
     */
    /**
     * Datos del comprador para emitir el comprobante.
     *
     * Antes solo se guardaba nombre/email/telefono y el DNI se perdia, asi que
     * las boletas salian sin identificar al cliente. Saga SI lo manda:
     *   - NationalRegistrationNumber → DNI (8) o RUC (11) del comprador.
     *   - InvoiceRequired            → true cuando pidio FACTURA.
     *   - ExtraBillingAttributes     → LegalId (RUC) y razon social cuando la pidio.
     *   - AddressBilling             → direccion fiscal + ubigeo (PostCode).
     */
    /**
     * Trae la orden cruda desde Saga (GetOrder). Se usa para completar los
     * pedidos viejos, que se guardaron sin el documento del comprador.
     */
    public function fetchRawOrder(string $externalOrderId): ?array
    {
        $res = $this->call('GetOrder', ['OrderId' => $externalOrderId]);

        $o = $res['Orders']['Order'] ?? $res['Order'] ?? null;

        // Con un solo resultado la API devuelve el objeto suelto.
        if (is_array($o) && isset($o[0])) {
            $o = $o[0];
        }

        return is_array($o) ? $o : null;
    }

    public function buildCustomerData(array $o): array
    {
        $billing = is_array($o['AddressBilling'] ?? null) ? $o['AddressBilling'] : [];
        $extra   = is_array($o['ExtraBillingAttributes'] ?? null) ? $o['ExtraBillingAttributes'] : [];

        $nombre = trim(($o['CustomerFirstName'] ?? '') . ' ' . ($o['CustomerLastName'] ?? ''));

        // Documento: manda el LegalId de facturacion si el comprador pidio
        // factura; si no, el registro nacional (que en Peru es el DNI).
        $doc = preg_replace('/\D+/', '', (string) (
            ($extra['LegalId'] ?? '') ?: ($o['NationalRegistrationNumber'] ?? '')
        ));

        $pidioFactura = filter_var($o['InvoiceRequired'] ?? false, FILTER_VALIDATE_BOOLEAN);

        return [
            'name'  => $nombre !== '' ? $nombre : 'Cliente',
            'email' => ($o['CustomerEmail'] ?? null) ?: ($billing['CustomerEmail'] ?? null) ?: null,
            'phone' => ($o['CustomerPhone'] ?? null) ?: ($billing['Phone'] ?? null) ?: null,

            // Lo que hace falta para el comprobante
            'document'       => $doc !== '' ? $doc : null,
            // Catalogo 06 de SUNAT: 1=DNI, 6=RUC, 4=Carnet de extranjeria.
            // Saga vende a extranjeros y esos documentos no son de 8 digitos
            // (visto: 006076365); sin tipo la boleta no se puede emitir a su
            // nombre. Longitud desconocida => se deja nulo y lo decide quien
            // emite, en vez de arriesgar un tipo equivocado.
            'document_type'  => match (true) {
                strlen($doc) === 8  => '1',
                strlen($doc) === 11 => '6',
                strlen($doc) >= 9   => '4',
                default             => null,
            },
            'invoice_required' => $pidioFactura,
            'legal_name'     => ($extra['ReceiverLegalName'] ?? '') ?: null,

            'billing' => array_filter([
                'address'  => trim(($billing['Address1'] ?? '') . ' ' . ($billing['Address2'] ?? '')) ?: null,
                'district' => $billing['Ward'] ?? null,
                'province' => $billing['City'] ?? null,
                'region'   => $billing['Region'] ?? null,
                'postcode' => $billing['PostCode'] ?? null,   // ubigeo de 6 digitos
                'country'  => $billing['Country'] ?? 'PE',
            ], fn ($v) => $v !== null && $v !== ''),
        ];
    }

    /**
     * ¿Esta cuenta expone datos de comprobante en la API?
     *
     * En carolayimport NINGUN pedido devuelve InvoiceNumber ni
     * InvoiceDocumentLink: los campos no vienen en la respuesta. Sin esto,
     * getOrderInvoiceNumber() devuelve null SIEMPRE y cualquier
     * reconciliacion "0 facturados" es un artefacto de medicion, no un
     * hallazgo. Se sondea un pedido y se decide si la comparacion vale.
     */
    public function supportsInvoiceLookup(string $externalOrderId): bool
    {
        try {
            $items = $this->getOrderItems($externalOrderId);
        } catch (\Throwable $e) {
            return false;
        }

        foreach ($items as $it) {
            if (!is_array($it)) continue;
            if (array_key_exists('InvoiceNumber', $it) || array_key_exists('InvoiceDocumentLink', $it)) {
                return true;
            }
        }

        return false;
    }

    public function getOrderInvoiceNumber(string $externalOrderId): ?string
    {
        $items = $this->getOrderItems($externalOrderId);
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $num = $it['InvoiceNumber'] ?? null;
            if (!empty($num)) {
                return (string) $num;
            }
            // Algunos catálogos exponen solo el link del documento ya cargado.
            if (!empty($it['InvoiceDocumentLink'])) {
                return 'documento';
            }
        }
        return null;
    }

    /**
     * Marca como "boleta ya cargada" los pedidos locales que Saga reporta como
     * facturados pero que NO se emitieron desde EBAEMY (document_id null). Evita
     * volver a pedir "Generar boleta" para pedidos históricos ya cumplidos.
     */
    public function detectExternalInvoices(): array
    {
        $locals = MarketplaceOrder::where('channel_id', $this->channel->id)
            ->whereNull('invoice_uploaded_at')
            ->whereNull('document_id')
            ->get();

        $checked = 0;
        $marked = 0;
        foreach ($locals as $mp) {
            try {
                $checked++;
                $num = $this->getOrderInvoiceNumber($mp->external_order_id);
                if ($num) {
                    $mp->invoice_uploaded_at = $mp->ordered_at ?? now();
                    $mp->invoice_upload_error = 'Boleta externa en Saga: ' . $num;
                    $mp->save();
                    $marked++;
                }
            } catch (\Throwable $e) {
                // best-effort; sigue con el resto
            }
        }
        return ['processed' => $checked, 'success' => $marked, 'failed' => 0];
    }

    /**
     * Sube el comprobante (boleta) del pedido a Saga — acción SetInvoicePDF.
     * Cierra el cumplimiento que causó el delisting ("carga de comprobantes").
     *
     * @param  array   $orderItemIds  IDs de los items del pedido en Saga
     * @param  string  $invoiceNumber Número del CPE (debe coincidir, ej "B001-123")
     * @param  string  $invoiceDate   Fecha de emisión (Y-m-d)
     * @param  string  $pdfBase64     PDF de la boleta en base64
     * @param  string  $invoiceType   BOLETA | FACTURA | NOTA_DE_CREDITO
     * @see https://developers.falabella.com/v600.0.0/reference/setinvoicepdf
     */
    public function setInvoicePDF(array $orderItemIds, string $invoiceNumber, string $invoiceDate, string $pdfBase64, string $invoiceType = 'BOLETA'): array
    {
        return $this->callPostForm('SetInvoicePDF', [
            'OrderItemIds'    => '[' . implode(',', $orderItemIds) . ']',
            'InvoiceNumber'   => $invoiceNumber,
            'InvoiceDate'     => $invoiceDate,
            'InvoiceType'     => $invoiceType,
            'OperatorCode'    => 'FAPE', // Falabella Perú
            'InvoiceDocument' => $pdfBase64,
        ]);
    }

    /**
     * Variante de POST para acciones con payload grande (ej. PDF base64): firma
     * TODOS los params (incluido el documento) pero los envía en el BODY como
     * form-urlencoded para no exceder el límite de longitud del query string.
     */
    protected function callPostForm(string $action, array $params = [])
    {
        $signed = $this->signRequest($action, $params);
        $response = Http::asForm()->timeout(60)->post($this->baseUrl, $signed);

        return $this->handleResponse($action, $response);
    }

    /**
     * Avisa al vendedor por email que entró un pedido nuevo de Saga.
     * Defensivo: nunca rompe el fetch de órdenes si el email falla.
     */
    protected function notifyNewOrder(MarketplaceOrder $order): void
    {
        try {
            $establishment = \App\Models\Tenant\Establishment::first();
            $email = $establishment->email ?? null;
            $store = $establishment->name ?? 'tu tienda';
            $customer = data_get($order->customer_data, 'name', 'Cliente');
            $total = number_format((float) $order->total, 2);
            $count = is_array($order->items_data) ? count($order->items_data) : 0;

            if ($email) {
                $body = "Nuevo pedido en Saga Falabella\n\n"
                    . "Pedido #{$order->external_order_id}\n"
                    . "Cliente: {$customer}\n"
                    . "Productos: {$count}\n"
                    . "Total: S/ {$total}\n\n"
                    . "Entra a tu panel → Marketplace para marcarlo listo y descargar la hoja de despacho.";

                \Illuminate\Support\Facades\Mail::raw($body, function ($m) use ($email, $store) {
                    $m->to($email)->subject("🛒 Nuevo pedido Saga Falabella — {$store}");
                });
            }

            Log::channel('payments')->info("Nuevo pedido Saga #{$order->external_order_id} (notificado a " . ($email ?: 'sin email') . ')');
        } catch (\Throwable $e) {
            Log::channel('payments')->warning("No se pudo notificar pedido Saga: {$e->getMessage()}");
        }
    }

    // ══════════════════════════════════════════════════════════════
    // PRICES — Actualizar precios
    // ══════════════════════════════════════════════════════════════

    public function syncPrices(): array
    {
        return MarketplaceSyncLog::log($this->channel->id, 'sync_prices', 'push', function ($log) {
            $mappings = MarketplaceProduct::where('channel_id', $this->channel->id)
                ->where('sync_status', '!=', 'excluded')
                ->whereNotNull('external_sku')
                ->where('external_sku', '!=', '')
                ->with(['item', 'variant'])
                ->get();

            $success = 0;
            $errors = [];
            foreach ($mappings as $mapping) {
                try {
                    $price = $mapping->variant
                        ? ($mapping->variant->sale_unit_price ?: $mapping->item->sale_unit_price)
                        : $mapping->item->sale_unit_price;

                    $skuXml = htmlspecialchars($mapping->external_sku);
                    $xml = '<?xml version="1.0" encoding="UTF-8"?><Request>'
                         . "<Product><SellerSku>{$skuXml}</SellerSku>"
                         . "<Price>" . number_format($price, 2, '.', '') . "</Price></Product>"
                         . '</Request>';

                    $this->callFeed('ProductPriceUpdate', $xml);
                    $mapping->update(['last_error' => null]);
                    $success++;
                } catch (\Throwable $e) {
                    $mapping->update(['last_error' => $e->getMessage()]);
                    $errors[] = ['sku' => $mapping->external_sku, 'error' => $e->getMessage()];
                    Log::channel('payments')->error('Falabella ProductPriceUpdate failed', [
                        'channel_id' => $this->channel->id,
                        'sku' => $mapping->external_sku,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ['processed' => $mappings->count(), 'success' => $success, 'failed' => $mappings->count() - $success, 'details' => $errors ?: null];
        })->toArray();
    }
}
