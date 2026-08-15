<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Models\Tenant\MarketplaceOrder;
use App\Models\Tenant\MarketplaceSyncLog;
use App\Models\Tenant\SagaCategoryMap;
use App\Models\Tenant\SagaCategoryAttribute;
use App\Models\Tenant\SagaBrandMap;
use App\Models\Tenant\Item;
use App\Models\Tenant\Document;
use Modules\Item\Models\Category;
use Modules\Item\Models\Brand;
use App\Services\Marketplace\MarketplaceOrchestrator;
use App\Services\Marketplace\MarketplaceInvoiceService;
use App\CoreFacturalo\Helpers\Storage\StorageDocument;
use Illuminate\Http\Request;

class MarketplaceController extends Controller
{
    use StorageDocument;

    public function index()
    {
        return view('ecommerce::configuration.marketplace');
    }

    /**
     * Homologacion de marcas ERP → Saga.
     *
     * `brand` es atributo OBLIGATORIO en toda categoria y no se puede mandar
     * el nombre interno: Saga exige una marca de su catalogo (10.814). El
     * backend existia sin pantalla y habia 0 marcas homologadas, asi que
     * ningun producto podia publicarse aunque tuviera todo lo demas.
     */
    public function sagaBrandsPanel()
    {
        $channel = MarketplaceChannel::where('platform', 'falabella')
                                     ->where('status', 'active')->first();

        if (!$channel) {
            return view('tenant.marketplace_orders.brands', [
                'channel' => null, 'rows' => collect(), 'sagaBrands' => [], 'sugeridas' => 0,
            ]);
        }

        $maps = SagaBrandMap::where('channel_id', $channel->id)->get()->keyBy('brand_id');

        $sagaBrands = [];
        $service = MarketplaceOrchestrator::resolveService($channel);
        if ($service && method_exists($service, 'getBrands')) {
            try {
                $sagaBrands = $service->getBrands();
            } catch (\Throwable $e) {
                \Log::channel('payments')->warning('Saga: no se pudieron traer marcas', ['error' => $e->getMessage()]);
            }
        }

        // Indice por nombre normalizado para proponer la coincidencia exacta.
        // Con 10.814 marcas, buscar a mano una por una es media hora perdida
        // cuando la mayoria coincide literal.
        $norm = fn ($t) => preg_replace('/[^A-Z0-9]/', '',
                    strtr(mb_strtoupper(trim((string) $t)), ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N']));

        $indice = [];
        foreach ($sagaBrands as $sb) {
            $k = $norm($sb['name'] ?? '');
            if ($k !== '' && !isset($indice[$k])) {
                $indice[$k] = $sb;
            }
        }

        $sugeridas = 0;

        $rows = Brand::orderBy('name')->get(['id', 'name'])->map(function ($b) use ($maps, $indice, $norm, &$sugeridas) {
            $m = $maps->get($b->id);
            $sug = (!$m && isset($indice[$norm($b->name)])) ? $indice[$norm($b->name)] : null;
            if ($sug) {
                $sugeridas++;
            }

            return (object) [
                'brand_id'   => $b->id,
                'name'       => $b->name,
                'saga_id'    => $m->saga_brand_id ?? null,
                'saga_name'  => $m->saga_brand_name ?? null,
                'sug_id'     => $sug['id'] ?? null,
                'sug_name'   => $sug['name'] ?? null,
            ];
        });

        return view('tenant.marketplace_orders.brands', compact('channel', 'rows', 'sagaBrands', 'sugeridas'));
    }

    /**
     * Atributos obligatorios por producto (Registro sanitario, Cantidad neta,
     * Condicion, etc.).
     *
     * NO se agregan columnas a `items` para esto: son atributos que solo le
     * importan a Saga, cambian POR CATEGORIA y algunos tienen lista cerrada de
     * valores. Viven en marketplace_products.attributes, que es donde el
     * editor y el constructor del payload ya los buscan.
     */
    public function sagaAttributesPanel(Request $request)
    {
        $channel = MarketplaceChannel::where('platform', 'falabella')
                                     ->where('status', 'active')->first();

        if (!$channel) {
            return view('tenant.marketplace_orders.attributes', [
                'channel' => null, 'rows' => collect(), 'sinHomologar' => 0,
            ]);
        }

        $maps = SagaCategoryMap::where('channel_id', $channel->id)->get()->keyBy('category_id');

        $productos = MarketplaceProduct::where('channel_id', $channel->id)
            ->where('sync_status', '!=', 'excluded')
            ->with('item')
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = trim($request->input('q'));
                $q->whereHas('item', fn ($w) => $w->where('description', 'like', "%{$t}%")
                                                  ->orWhere('internal_id', 'like', "%{$t}%"));
            })
            ->get();

        $sinHomologar = 0;
        $rows = collect();

        foreach ($productos as $p) {
            if (!$p->item) {
                continue;   // mapeo huerfano: el item se borro
            }

            $map = $maps->get($p->item->category_id);
            if (!$map) {
                $sinHomologar++;
                continue;   // primero hay que homologar su categoria
            }

            $faltan = [];
            try {
                $faltan = (new \App\Services\Marketplace\SagaProductPayloadBuilder($channel, $p))
                            ->missingMandatoryAttributes();
            } catch (\Throwable $e) {
                // Un producto raro no puede tumbar la pantalla entera.
            }

            $rows->push((object) [
                'id'       => $p->id,
                'nombre'   => $p->item->description,
                'codigo'   => $p->item->internal_id,
                'categoria'=> $map->saga_category_name ?: $map->saga_category_id,
                'faltan'   => $faltan,
            ]);
        }

        // Los que tienen faltantes primero: es lo unico accionable aqui.
        $rows = $rows->sortByDesc(fn ($r) => count($r->faltan))->values();

        return view('tenant.marketplace_orders.attributes', compact('channel', 'rows', 'sinHomologar'));
    }

    /**
     * Peso y medidas del paquete — el dato que frena mas publicaciones.
     *
     * Saga exige package_weight/length/width/height como obligatorios en toda
     * categoria. Los campos ya existen en items y el payload ya sabe
     * enviarlos: lo que falta es cargarlos. Son cientos de productos, asi que
     * hay edicion en linea Y carga por CSV.
     */
    public function sagaDimensionsPanel(Request $request)
    {
        $soloFaltan = !$request->has('todos');

        $items = Item::query()
            ->when($soloFaltan, function ($q) {
                // "Falta" = cualquiera de los cuatro sin valor. Saga los pide
                // todos, asi que tener solo el peso no sirve de nada.
                $q->where(function ($w) {
                    foreach (['weight', 'length', 'width', 'height'] as $c) {
                        $w->orWhereNull($c)->orWhere($c, '<=', 0);
                    }
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $t = trim($request->input('q'));
                $q->where(function ($w) use ($t) {
                    $w->where('description', 'like', "%{$t}%")
                      ->orWhere('internal_id', 'like', "%{$t}%");
                });
            })
            ->orderBy('description')
            ->paginate(50)
            ->withQueryString();

        $completos = Item::where('weight', '>', 0)->where('length', '>', 0)
                         ->where('width', '>', 0)->where('height', '>', 0)->count();

        return view('tenant.marketplace_orders.dimensions', [
            'items'      => $items,
            'total'      => Item::count(),
            'completos'  => $completos,
            'soloFaltan' => $soloFaltan,
            'q'          => $request->input('q', ''),
        ]);
    }

    /** Guarda peso y medidas de un producto (edicion en linea). */
    public function saveItemDimensions(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|integer',
            'weight'  => 'nullable|numeric|min:0|max:9999',
            'length'  => 'nullable|numeric|min:0|max:9999',
            'width'   => 'nullable|numeric|min:0|max:9999',
            'height'  => 'nullable|numeric|min:0|max:9999',
        ]);

        $item = Item::find($data['item_id']);
        if (!$item) {
            return response()->json(['error' => 'Producto no encontrado'], 404);
        }

        $item->update([
            'weight' => $data['weight'] ?: null,
            'length' => $data['length'] ?: null,
            'width'  => $data['width']  ?: null,
            'height' => $data['height'] ?: null,
        ]);

        $completo = $item->weight > 0 && $item->length > 0 && $item->width > 0 && $item->height > 0;

        return response()->json(['success' => true, 'complete' => $completo]);
    }

    /** Exporta el CSV para llenar peso y medidas fuera del sistema. */
    public function exportDimensions(Request $request)
    {
        $items = Item::orderBy('description')->get(['id', 'internal_id', 'description', 'weight', 'length', 'width', 'height']);

        return response()->streamDownload(function () use ($items) {
            $out = fopen('php://output', 'w');
            // BOM real (3 bytes) para que Excel respete los acentos. Escrito
            // con chr() y no con la secuencia literal: al pegarla como texto
            // quedaban 6 bytes y Excel mostraba "ï»¿" en la primera celda.
            fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['id', 'codigo', 'producto', 'peso_kg', 'largo_cm', 'ancho_cm', 'alto_cm'], ';');
            foreach ($items as $i) {
                fputcsv($out, [$i->id, $i->internal_id, $i->description,
                               $i->weight, $i->length, $i->width, $i->height], ';');
            }
            fclose($out);
        }, 'peso-y-medidas.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Importa el CSV. La columna `id` manda: el codigo puede repetirse o
     * cambiar, el id no. Se informa fila por fila lo que no se pudo aplicar,
     * en vez de fallar entero.
     */
    public function importDimensions(Request $request)
    {
        $request->validate(['archivo' => 'required|file|mimes:csv,txt|max:5120']);

        $ruta = $request->file('archivo')->getRealPath();
        $fh = fopen($ruta, 'r');
        if (!$fh) {
            return back()->with('error', 'No se pudo leer el archivo.');
        }

        $cabecera = fgetcsv($fh, 0, ';');
        // Excel a veces guarda con coma: si la cabecera no se partio, reintentar.
        $sep = (is_array($cabecera) && count($cabecera) > 1) ? ';' : ',';
        if ($sep === ',') {
            rewind($fh);
            $cabecera = fgetcsv($fh, 0, ',');
        }

        $aplicados = 0; $saltados = 0; $avisos = [];

        while (($fila = fgetcsv($fh, 0, $sep)) !== false) {
            $id = (int) ($fila[0] ?? 0);
            if ($id <= 0) { $saltados++; continue; }

            $item = Item::find($id);
            if (!$item) {
                $saltados++;
                if (count($avisos) < 5) $avisos[] = "id {$id}: no existe";
                continue;
            }

            $num = function ($v) {
                $v = str_replace(',', '.', trim((string) $v));   // 1,5 → 1.5
                return is_numeric($v) && (float) $v > 0 ? (float) $v : null;
            };

            $item->update([
                'weight' => $num($fila[3] ?? null),
                'length' => $num($fila[4] ?? null),
                'width'  => $num($fila[5] ?? null),
                'height' => $num($fila[6] ?? null),
            ]);
            $aplicados++;
        }
        fclose($fh);

        $msg = "Actualizados: {$aplicados}." . ($saltados ? " Sin aplicar: {$saltados}." : '');
        if ($avisos) $msg .= ' ' . implode(' · ', $avisos);

        return back()->with($aplicados ? 'success' : 'error', $msg);
    }

    /**
     * Pantalla de homologacion de categorias ERP → Saga.
     *
     * Saga no acepta el nombre de la categoria interna: exige el ID de su
     * propio arbol. Sin homologar, el producto se rechaza con "Categoria X
     * sin homologar" y no hay forma de publicarlo. El backend existia; esta
     * es la pantalla que faltaba.
     */
    public function sagaCategoriesPanel()
    {
        $channel = MarketplaceChannel::where('platform', 'falabella')
                                     ->where('status', 'active')->first();

        if (!$channel) {
            return view('tenant.marketplace_orders.categories', [
                'channel' => null, 'rows' => collect(), 'pendientes' => collect(), 'mapped' => 0,
            ]);
        }

        $maps = SagaCategoryMap::where('channel_id', $channel->id)->get()->keyBy('category_id');

        $rows = Category::orderBy('name')->get(['id', 'name'])->map(function ($c) use ($maps) {
            $m = $maps->get($c->id);
            return (object) [
                'category_id' => $c->id,
                'name'        => $c->name,
                'saga_id'     => $m->saga_category_id ?? null,
                'saga_name'   => $m->saga_category_name ?? null,
                'saga_path'   => $m->saga_category_path ?? null,
            ];
        });

        // Las categorias que estan frenando productos van primero: son las que
        // de verdad urge homologar, y sin esto se pierden entre las demas.
        $pendientes = MarketplaceProduct::where('channel_id', $channel->id)
            ->where('sync_status', 'error')
            ->pluck('last_error')
            ->map(function ($e) {
                preg_match('/Categor[ií]a "([^"]+)"/u', (string) $e, $m);
                return $m[1] ?? null;
            })
            ->filter()->countBy()->sortDesc();

        return view('tenant.marketplace_orders.categories', [
            'channel'    => $channel,
            'rows'       => $rows,
            'pendientes' => $pendientes,
            'mapped'     => $maps->count(),
        ]);
    }

    /**
     * Panel de pedidos del canal externo (Saga Falabella).
     *
     * Existia todo el backend de facturacion sin una pantalla desde donde
     * usarlo. Se hace en Blade y no en Vue a proposito: no obliga a
     * recompilar el bundle para desplegarlo.
     */
    public function ordersPanel(Request $request)
    {
        $channel = MarketplaceChannel::where('platform', 'falabella')
                                     ->where('status', 'active')->first();

        if (!$channel) {
            return view('tenant.marketplace_orders.index', [
                'channel' => null, 'orders' => collect(), 'metrics' => [], 'filter' => 'todos', 'q' => '',
            ]);
        }

        $q      = trim((string) $request->input('q', ''));
        $filter = $request->input('filter', 'todos');

        $base = fn () => MarketplaceOrder::where('channel_id', $channel->id);

        // "Facturado" = emitido desde EBAEMY. Lo marcado como externo se
        // cuenta aparte: no es lo mismo tener el comprobante en el sistema
        // que asumir que alguien lo hizo por fuera.
        $metrics = [
            'todos'     => $base()->count(),
            'sin_doc'   => $base()->whereNull('document_id')->whereNull('invoice_uploaded_at')->count(),
            'emitidos'  => $base()->whereNotNull('document_id')->count(),
            'externos'  => $base()->whereNull('document_id')->whereNotNull('invoice_uploaded_at')->count(),
        ];

        $orders = $base()
            ->when($filter === 'sin_doc',  fn ($x) => $x->whereNull('document_id')->whereNull('invoice_uploaded_at'))
            ->when($filter === 'emitidos', fn ($x) => $x->whereNotNull('document_id'))
            ->when($filter === 'externos', fn ($x) => $x->whereNull('document_id')->whereNotNull('invoice_uploaded_at'))
            ->when($q !== '', function ($x) use ($q) {
                $x->where(function ($w) use ($q) {
                    $w->where('external_order_id', 'like', "%{$q}%")
                      ->orWhere('customer_data', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('ordered_at')
            ->paginate(30)
            ->withQueryString();

        return view('tenant.marketplace_orders.index', compact('channel', 'orders', 'metrics', 'filter', 'q'));
    }

    public function productsByChannel()
    {
        return view('ecommerce::configuration.marketplace_products');
    }

    /**
     * Guardar asignación masiva de productos a un canal.
     */
    public function saveChannelProducts(Request $request, int $channelId)
    {
        $items = $request->input('items', []);
        $saved = 0;
        $removed = 0;

        foreach ($items as $item) {
            $itemId = $item['item_id'] ?? null;
            if (!$itemId) continue;

            if ($item['active'] ?? false) {
                MarketplaceProduct::updateOrCreate(
                    ['channel_id' => $channelId, 'item_id' => $itemId],
                    [
                        'external_sku' => $item['external_sku'] ?? null,
                        'sync_status'  => 'pending',
                    ]
                );
                $saved++;
            } else {
                MarketplaceProduct::where('channel_id', $channelId)
                    ->where('item_id', $itemId)
                    ->delete();
                $removed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "{$saved} productos asignados, {$removed} removidos",
        ]);
    }

    /**
     * Convertir un pedido de marketplace en un Order regular.
     * Así aparece en la lista de Pedidos junto con los del ecommerce.
     */
    public function convertToOrder(int $marketplaceOrderId)
    {
        $mpOrder = MarketplaceOrder::findOrFail($marketplaceOrderId);

        if ($mpOrder->order_id) {
            return response()->json([
                'success' => false,
                'message' => 'Este pedido ya fue convertido (Order #' . $mpOrder->order_id . ')',
            ], 422);
        }

        // Crea y enlaza el Order interno (lógica compartida con la auto-conversión
        // del fetch). No tocamos el status de despacho aquí.
        $order = $mpOrder->createErpOrder();

        return response()->json([
            'success' => true,
            'message' => 'Pedido convertido a Order #' . $order->id,
            'order_id' => $order->id,
        ]);
    }

    // ── Channels CRUD ──────────────────────────────────────────

    public function channels()
    {
        return response()->json(MarketplaceChannel::latest()->get());
    }

    public function storeChannel(Request $request)
    {
        $request->validate([
            'platform' => 'required|in:falabella,meta,mercadolibre,tiktok',
            'name' => 'required|string|max:100',
        ]);

        $channel = MarketplaceChannel::create([
            'platform' => $request->platform,
            'name' => $request->name,
            'credentials' => $request->credentials ?? [],
            'settings' => $request->settings ?? ['auto_sync' => true, 'sync_interval' => 30],
        ]);

        return response()->json(['success' => true, 'channel' => $channel]);
    }

    public function updateChannel(Request $request, int $id)
    {
        $channel = MarketplaceChannel::findOrFail($id);
        $channel->update($request->only(['name', 'status', 'credentials', 'settings']));
        return response()->json(['success' => true]);
    }

    /**
     * Activa/desactiva el auto-publish a Saga del canal (Fase 4). Con ON, cada
     * producto nuevo con apply_store se publica solo; los ya enlazados se
     * re-sincronizan al editarlos (eso es independiente de este flag).
     */
    public function toggleAutoPublish(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $settings = $channel->settings ?? [];
        $settings['auto_publish'] = !($settings['auto_publish'] ?? false);
        $channel->update(['settings' => $settings]);

        return response()->json([
            'success' => true,
            'auto_publish' => $settings['auto_publish'],
            'message' => $settings['auto_publish']
                ? 'Auto-publicación activada: los productos nuevos se publicarán en Saga.'
                : 'Auto-publicación desactivada.',
        ]);
    }

    // ── Product Mapping ────────────────────────────────────────

    public function products(int $channelId)
    {
        $products = MarketplaceProduct::where('channel_id', $channelId)
            ->with(['item:id,description,internal_id,sale_unit_price,stock', 'variant:id,display_name,sku,stock'])
            ->paginate(50);

        return response()->json($products);
    }

    public function mapProduct(Request $request)
    {
        $request->validate([
            'channel_id' => 'required|integer',
            'item_id' => 'required|integer',
            'external_sku' => 'required|string|max:100',
        ]);

        $mapping = MarketplaceProduct::updateOrCreate(
            [
                'channel_id' => $request->channel_id,
                'item_id' => $request->item_id,
                'item_variant_id' => $request->item_variant_id,
            ],
            [
                'external_sku' => $request->external_sku,
                'sync_status' => 'pending',
            ]
        );

        return response()->json(['success' => true, 'mapping' => $mapping]);
    }

    public function autoMapProducts(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);

        // Auto-mapear: SKU interno = SKU externo para items con apply_store=1
        $items = Item::where('apply_store', 1)->whereNotNull('internal_id')->get();
        $mapped = 0;

        foreach ($items as $item) {
            if ($item->has_variants && $item->variants->count() > 0) {
                foreach ($item->variants->where('is_active', true) as $variant) {
                    MarketplaceProduct::firstOrCreate([
                        'channel_id' => $channelId,
                        'item_id' => $item->id,
                        'item_variant_id' => $variant->id,
                    ], [
                        'external_sku' => $variant->sku ?: "{$item->internal_id}-V{$variant->id}",
                        'sync_status' => 'pending',
                    ]);
                    $mapped++;
                }
            } else {
                MarketplaceProduct::firstOrCreate([
                    'channel_id' => $channelId,
                    'item_id' => $item->id,
                ], [
                    'external_sku' => $item->internal_id,
                    'sync_status' => 'pending',
                ]);
                $mapped++;
            }
        }

        return response()->json(['success' => true, 'mapped' => $mapped]);
    }

    // ── Sync Actions ───────────────────────────────────────────

    public function syncProducts(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'syncProducts')) {
            return response()->json(['error' => 'Service not available'], 400);
        }

        $result = $service->syncProducts();
        return response()->json($result);
    }

    /**
     * Resuelve el estado de los productos enviados con ProductCreate (asíncrono):
     * consulta FeedStatus/QC y actualiza external_id, qc_status y motivos de rechazo.
     */
    public function syncFeedStatuses(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'syncFeedStatuses')) {
            return response()->json(['error' => 'Acción no disponible para este canal'], 400);
        }

        $result = $service->syncFeedStatuses();
        return response()->json($result);
    }

    /**
     * Reintenta publicar UN producto puntual (botón "Reintentar" en la lista).
     * Reutiliza FalabellaService::publishOne (valida + push, deja last_error).
     */
    public function retryProduct(int $channelId, int $productId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $mapping = MarketplaceProduct::where('channel_id', $channelId)->findOrFail($productId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'publishOne')) {
            return response()->json(['error' => 'Acción no disponible para este canal'], 400);
        }

        $result = $service->publishOne($mapping);
        return response()->json([
            'success' => $result['success'] ?? false,
            'message' => ($result['success'] ?? false)
                ? 'Producto reenviado a Saga.'
                : ('No se pudo publicar: ' . ($result['error'] ?? 'error desconocido')),
        ]);
    }

    public function syncStock(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'syncStock')) {
            return response()->json(['error' => 'Service not available'], 400);
        }

        $result = $service->syncStock();
        return response()->json($result);
    }

    public function fetchOrders(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'fetchOrders')) {
            return response()->json(['error' => 'Service not available'], 400);
        }

        $result = $service->fetchOrders();
        return response()->json($result);
    }

    /**
     * Importa el catálogo de Saga Falabella HACIA EBAEMY, por lotes.
     * El frontend llama este endpoint en tandas (offset += limit) y muestra el
     * progreso. Cada llamada es corta para no exceder el timeout del servidor.
     */
    public function importCatalog(Request $request, int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);

        if ($channel->platform !== 'falabella') {
            return response()->json(['error' => 'La importación de catálogo solo está disponible para Saga Falabella'], 400);
        }

        $offset     = max(0, (int) $request->input('offset', 0));
        $limit      = min(25, max(1, (int) $request->input('limit', 10)));
        $withImages = $request->boolean('with_images', true);
        $dryRun     = $request->boolean('dry_run', false);

        try {
            $service = new \App\Services\Marketplace\FalabellaImportService($channel, $withImages);
            $summary = $service->import($dryRun, $limit, $offset);

            // 'done' cuando Saga devolvió menos productos que el lote pedido.
            $summary['done'] = $summary['fetched'] < $limit;
            $summary['next_offset'] = $offset + $limit;

            // No devolvemos el detalle completo de filas (puede ser grande).
            unset($summary['rows']);

            return response()->json($summary);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage(), 'done' => true], 500);
        }
    }

    // ── Homologación de categorías (Saga) ──────────────────────

    /**
     * Categorías internas con su homologación actual a Saga (solo lectura, BD).
     * El árbol de Saga se trae aparte (sagaCategoryTree) porque es una llamada
     * a la API potencialmente pesada.
     */
    public function sagaCategories(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);

        $maps = SagaCategoryMap::where('channel_id', $channelId)
            ->get()
            ->keyBy('category_id');

        $categories = Category::orderBy('name')->get(['id', 'name'])->map(function ($c) use ($maps) {
            $m = $maps->get($c->id);
            return [
                'category_id'        => $c->id,
                'name'               => $c->name,
                'saga_category_id'   => $m->saga_category_id ?? null,
                'saga_category_name' => $m->saga_category_name ?? null,
                'saga_category_path' => $m->saga_category_path ?? null,
            ];
        });

        return response()->json([
            'platform'   => $channel->platform,
            'categories' => $categories,
            'mapped'     => $maps->count(),
            'total'      => $categories->count(),
        ]);
    }

    /**
     * Trae el árbol de categorías de Saga (en vivo) aplanado. Solo hojas, que
     * son las asignables como PrimaryCategory.
     */
    public function sagaCategoryTree(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        if ($channel->platform !== 'falabella') {
            return response()->json(['error' => 'Solo disponible para Saga Falabella'], 400);
        }

        $service = MarketplaceOrchestrator::resolveService($channel);
        if (!$service || !method_exists($service, 'getCategoryTree')) {
            return response()->json(['error' => 'Servicio no disponible'], 400);
        }

        try {
            $tree = collect($service->getCategoryTree())
                ->filter(fn ($n) => $n['is_leaf'])   // solo hojas
                ->values()
                ->all();
            return response()->json(['leaves' => $tree, 'count' => count($tree)]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Guarda (o borra) la homologación de una categoría interna → categoría Saga.
     * Al asignar, cachea los atributos obligatorios de esa categoría Saga para
     * que el builder del payload (Fase 2) los conozca sin re-llamar a la API.
     */
    public function saveSagaCategory(Request $request, int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $data = $request->validate([
            'category_id'        => 'required|integer',
            'saga_category_id'   => 'nullable|string|max:60',
            'saga_category_name' => 'nullable|string|max:191',
            'saga_category_path' => 'nullable|string|max:500',
        ]);

        // Sin saga_category_id → desmapear.
        if (empty($data['saga_category_id'])) {
            SagaCategoryMap::where('channel_id', $channelId)
                ->where('category_id', $data['category_id'])
                ->delete();
            return response()->json(['success' => true, 'message' => 'Homologación eliminada']);
        }

        SagaCategoryMap::updateOrCreate(
            ['channel_id' => $channelId, 'category_id' => $data['category_id']],
            [
                'saga_category_id'   => $data['saga_category_id'],
                'saga_category_name' => $data['saga_category_name'] ?? null,
                'saga_category_path' => $data['saga_category_path'] ?? null,
            ]
        );

        // Cachear atributos obligatorios (best-effort: no bloquea el guardado).
        $required = [];
        $service = MarketplaceOrchestrator::resolveService($channel);
        if ($service && method_exists($service, 'getCategoryAttributes')) {
            try {
                $attrs = $service->getCategoryAttributes($data['saga_category_id']);
                SagaCategoryAttribute::updateOrCreate(
                    ['channel_id' => $channelId, 'saga_category_id' => $data['saga_category_id']],
                    ['attributes' => $attrs, 'fetched_at' => now()]
                );
                $required = array_values(array_filter($attrs, fn ($a) => $a['mandatory']));
            } catch (\Throwable $e) {
                \Log::channel('payments')->warning('Saga: no se pudieron traer atributos de categoría', [
                    'channel_id' => $channelId,
                    'saga_category_id' => $data['saga_category_id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Categoría homologada',
            'required_attributes' => $required,
            'required_count' => count($required),
        ]);
    }

    /**
     * Devuelve los atributos de una categoría Saga (desde caché; si no existe,
     * los trae en vivo y los cachea).
     */
    public function sagaCategoryAttributes(int $channelId, string $sagaCategoryId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);

        $cached = SagaCategoryAttribute::where('channel_id', $channelId)
            ->where('saga_category_id', $sagaCategoryId)
            ->first();

        if ($cached) {
            return response()->json(['attributes' => $cached->attributes ?? [], 'cached' => true]);
        }

        $service = MarketplaceOrchestrator::resolveService($channel);
        if (!$service || !method_exists($service, 'getCategoryAttributes')) {
            return response()->json(['error' => 'Servicio no disponible'], 400);
        }

        try {
            $attrs = $service->getCategoryAttributes($sagaCategoryId);
            SagaCategoryAttribute::updateOrCreate(
                ['channel_id' => $channelId, 'saga_category_id' => $sagaCategoryId],
                ['attributes' => $attrs, 'fetched_at' => now()]
            );
            return response()->json(['attributes' => $attrs, 'cached' => false]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Homologación de marcas (Saga valida la marca contra su catálogo) ──

    /**
     * Devuelve las marcas internas con su homologación actual + el catálogo de
     * marcas de Saga (GetBrands en vivo) para poblar los selects.
     */
    public function sagaBrands(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        if ($channel->platform !== 'falabella') {
            return response()->json(['error' => 'Solo disponible para Saga Falabella'], 400);
        }

        $maps = SagaBrandMap::where('channel_id', $channelId)->get()->keyBy('brand_id');

        $brands = Brand::orderBy('name')->get(['id', 'name'])->map(function ($b) use ($maps) {
            $m = $maps->get($b->id);
            return [
                'brand_id'        => $b->id,
                'name'            => $b->name,
                'saga_brand_id'   => $m->saga_brand_id ?? null,
                'saga_brand_name' => $m->saga_brand_name ?? null,
            ];
        });

        // Catálogo de marcas de Saga (en vivo). Best-effort: si falla, devolvemos
        // el listado interno igual para no bloquear la pantalla.
        $sagaBrands = [];
        $service = MarketplaceOrchestrator::resolveService($channel);
        if ($service && method_exists($service, 'getBrands')) {
            try {
                $sagaBrands = $service->getBrands();
            } catch (\Throwable $e) {
                \Log::channel('payments')->warning('Saga: no se pudieron traer marcas', [
                    'channel_id' => $channelId, 'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'brands'      => $brands,
            'saga_brands' => $sagaBrands,
            'mapped'      => $maps->count(),
            'total'       => $brands->count(),
        ]);
    }

    /**
     * Guarda (o borra) la homologación de una marca interna → marca Saga.
     */
    public function saveSagaBrand(Request $request, int $channelId)
    {
        MarketplaceChannel::findOrFail($channelId);
        $data = $request->validate([
            'brand_id'        => 'required|integer',
            'saga_brand_id'   => 'nullable|string|max:60',
            'saga_brand_name' => 'nullable|string|max:191',
        ]);

        if (empty($data['saga_brand_name'])) {
            SagaBrandMap::where('channel_id', $channelId)
                ->where('brand_id', $data['brand_id'])
                ->delete();
            return response()->json(['success' => true, 'message' => 'Homologación de marca eliminada']);
        }

        SagaBrandMap::updateOrCreate(
            ['channel_id' => $channelId, 'brand_id' => $data['brand_id']],
            [
                'saga_brand_id'   => $data['saga_brand_id'] ?? null,
                'saga_brand_name' => $data['saga_brand_name'],
            ]
        );

        return response()->json(['success' => true, 'message' => 'Marca homologada']);
    }

    // ── Atributos obligatorios por producto ────────────────────

    /**
     * Esquema de atributos de la categoría Saga del producto + los valores ya
     * guardados, para pintar el editor. Resuelve: producto → item → categoría
     * interna → categoría Saga homologada → atributos cacheados.
     */
    public function productAttributes(int $channelId, int $productId)
    {
        MarketplaceChannel::findOrFail($channelId);
        $product = MarketplaceProduct::where('channel_id', $channelId)
            ->where('id', $productId)
            ->with('item')
            ->firstOrFail();

        $categoryId = $product->item->category_id ?? null;
        if (!$categoryId) {
            return response()->json(['error' => 'El producto no tiene categoría interna asignada.'], 422);
        }

        $map = SagaCategoryMap::where('channel_id', $channelId)
            ->where('category_id', $categoryId)
            ->first();
        if (!$map) {
            return response()->json(['error' => 'La categoría de este producto aún no está homologada a Saga. Hazlo en "Categorías".'], 422);
        }

        $cache = SagaCategoryAttribute::where('channel_id', $channelId)
            ->where('saga_category_id', $map->saga_category_id)
            ->first();

        return response()->json([
            'attributes'  => $cache->attributes ?? [],
            'values'      => $product->attributes ?? (object) [],
            'category'    => $map->saga_category_name ?: $map->saga_category_id,
            'item_name'   => $product->item->description ?? ('Item #' . $product->item_id),
        ]);
    }

    /**
     * Guarda los valores de atributos del producto (mapa nombre→valor). Marca el
     * mapeo como 'pending' para que el próximo sync/publish lo reenvíe con los
     * datos completos.
     */
    public function saveProductAttributes(Request $request, int $channelId, int $productId)
    {
        MarketplaceChannel::findOrFail($channelId);
        $product = MarketplaceProduct::where('channel_id', $channelId)
            ->where('id', $productId)
            ->firstOrFail();

        $values = $request->input('values', []);
        if (!is_array($values)) {
            $values = [];
        }
        // Solo pares string/escalar no vacíos.
        $clean = [];
        foreach ($values as $k => $v) {
            if (is_string($k) && $k !== '' && $v !== null && $v !== '') {
                $clean[$k] = is_array($v) ? $v : (string) $v;
            }
        }

        $product->attributes = $clean ?: null;
        // Si estaba excluido lo dejamos como está; si no, lo marcamos pendiente
        // para reenviar con los atributos completos.
        if ($product->sync_status !== 'excluded') {
            $product->sync_status = 'pending';
        }
        $product->save();

        return response()->json([
            'success' => true,
            'message' => 'Atributos guardados. Se reenviarán a Saga en el próximo sync/publicación.',
            'count'   => count($clean),
        ]);
    }

    // ── Fulfillment / Despacho ─────────────────────────────────

    /**
     * Deriva los parámetros de despacho desde los items de la orden de Saga.
     * @return array{0: array, 1: string, 2: string, 3: ?string}  [ids, deliveryType, provider, tracking]
     */
    protected function dispatchParams(MarketplaceOrder $order, $service = null): array
    {
        $items = is_array($order->items_data) ? $order->items_data : [];
        if (isset($items['OrderItemId'])) $items = [$items];

        [$ids, $deliveryType, $provider, $tracking] = $this->parseDispatchItems($items);

        // Fallback en vivo: si el snapshot no trae OrderItemId (orden vieja o hipo
        // en GetOrderItems al fetch), consultamos a Saga en el momento. Evita el
        // 422 "no tiene items" silencioso cuando el pedido sí es despachable.
        if (empty($ids) && $service && method_exists($service, 'getOrderItems')) {
            try {
                $live = $service->getOrderItems($order->external_order_id);
                [$ids, $deliveryType, $provider, $tracking] = $this->parseDispatchItems($live);
            } catch (\Throwable $e) {
                // Sin snapshot ni respuesta en vivo: dejamos $ids vacío y el caller
                // responde 422 con su mensaje habitual.
            }
        }

        return [$ids, $deliveryType, $provider, $tracking];
    }

    /**
     * Extrae [ids, deliveryType, provider, tracking] de una lista de OrderItems.
     */
    protected function parseDispatchItems(array $items): array
    {
        $ids = [];
        $provider = 'falabella';
        $deliveryType = 'dropship';
        $tracking = null;

        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $id = $it['OrderItemId'] ?? null;
            if ($id) $ids[] = $id;
            $provider = $it['ShipmentProvider'] ?? $provider;
            if (stripos($it['ShippingType'] ?? '', 'dropship') !== false) $deliveryType = 'dropship';
            $tracking = $it['TrackingCode'] ?? $tracking;
        }

        return [$ids, $deliveryType, $provider, $tracking];
    }

    public function markOrderReady(int $channelId, int $orderId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $order = MarketplaceOrder::where('channel_id', $channelId)->findOrFail($orderId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'setReadyToShip')) {
            return response()->json(['error' => 'Acción no disponible para este canal'], 400);
        }

        // Idempotencia: si ya está listo/enviado, no re-llamar a Saga.
        if (in_array($order->status, ['ready_to_ship', 'shipped'], true)) {
            return response()->json(['success' => true, 'message' => 'El pedido ya estaba listo para despacho']);
        }

        [$ids, $deliveryType, $provider, $tracking] = $this->dispatchParams($order, $service);
        if (empty($ids)) {
            return response()->json(['error' => 'La orden no tiene items para despachar'], 422);
        }

        try {
            $service->setReadyToShip($ids, $deliveryType, $provider, $tracking);
            $order->update(['status' => 'ready_to_ship']);
            $order->syncErpOrderStatus();
            \Log::channel('payments')->info("Saga: pedido #{$order->external_order_id} marcado READY", [
                'order_id' => $order->id, 'channel_id' => $channelId, 'items' => $ids,
            ]);
            return response()->json(['success' => true, 'message' => 'Pedido marcado como listo para despacho']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function markOrderShipped(int $channelId, int $orderId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $order = MarketplaceOrder::where('channel_id', $channelId)->findOrFail($orderId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'setShipped')) {
            return response()->json(['error' => 'Acción no disponible para este canal'], 400);
        }

        // Idempotencia: si ya está enviado, no re-llamar a Saga.
        if ($order->status === 'shipped') {
            return response()->json(['success' => true, 'message' => 'El pedido ya estaba marcado como enviado']);
        }

        [$ids, $deliveryType, $provider, $tracking] = $this->dispatchParams($order, $service);
        if (empty($ids)) {
            return response()->json(['error' => 'La orden no tiene items'], 422);
        }

        try {
            $service->setShipped($ids, $deliveryType, $provider, $tracking);
            $order->update(['status' => 'shipped']);
            $order->syncErpOrderStatus();
            \Log::channel('payments')->info("Saga: pedido #{$order->external_order_id} marcado SHIPPED", [
                'order_id' => $order->id, 'channel_id' => $channelId, 'items' => $ids,
            ]);
            return response()->json(['success' => true, 'message' => 'Pedido marcado como enviado']);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Descarga un documento del pedido (hoja de despacho, boleta/factura).
     */
    public function downloadDocument(int $channelId, int $orderId, string $type)
    {
        $allowed = ['shippingLabel', 'invoice', 'carrierManifest'];
        if (!in_array($type, $allowed, true)) {
            abort(400, 'Tipo de documento inválido');
        }

        $channel = MarketplaceChannel::findOrFail($channelId);
        $order = MarketplaceOrder::where('channel_id', $channelId)->findOrFail($orderId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'getDocument')) {
            abort(400, 'Acción no disponible para este canal');
        }

        [$ids] = $this->dispatchParams($order, $service);
        if (empty($ids)) {
            abort(422, 'La orden no tiene items');
        }

        try {
            $doc = $service->getDocument($ids, $type);
        } catch (\Throwable $e) {
            abort(500, $e->getMessage());
        }

        if (empty($doc['file'])) {
            abort(404, 'Saga no devolvió el documento (¿el pedido ya está listo para despacho?)');
        }

        $binary = base64_decode($doc['file']);
        $mime = $doc['mime'] ?: 'application/pdf';
        $ext = str_contains($mime, 'pdf') ? 'pdf' : (str_contains($mime, 'html') ? 'html' : 'bin');
        $filename = "{$type}_{$order->external_order_id}.{$ext}";

        return response($binary, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /**
     * Genera la BOLETA electrónica (SUNAT) del pedido de Saga.
     * Solo si el pedido ya está listo/enviado (restricción de Saga).
     */
    public function generateInvoice(int $channelId, int $orderId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $order = MarketplaceOrder::where('channel_id', $channelId)->findOrFail($orderId);

        // ── Cuando SI se puede emitir ────────────────────────────────────
        //
        // El riesgo real es la boleta huerfana: si se emite antes de que el
        // cliente reciba, y luego devuelve, en Peru esa boleta solo se deshace
        // con NOTA DE CREDITO. Ojo con esto: reconcileReturns() trae de Saga
        // los 'returned' y los guarda como 'canceled', asi que una devolucion
        // termina siendo un pedido cancelado CON boleta emitida.
        //
        // Por eso el estado seguro es ENTREGADO. 'shipped' se permite pero
        // exigiendo confirmacion explicita: el paquete viaja y todavia puede
        // rechazarse.
        $bloqueos = [
            'pending'       => 'Este pedido reciEn ingreso y todavia no se despacha. Espera a que se entregue.',
            'ready_to_ship' => 'El pedido aun no sale de tu almacen. Espera a que se entregue al cliente.',
            'canceled'      => 'Este pedido esta CANCELADO o fue devuelto. Emitir una boleta aqui te obligaria a anularla con nota de credito.',
            'returned'      => 'Este pedido fue DEVUELTO. No corresponde emitir boleta.',
            'failed'        => 'Este pedido fallo en Saga. No corresponde emitir boleta.',
        ];

        if (isset($bloqueos[$order->status])) {
            return response()->json(['error' => $bloqueos[$order->status]], 422);
        }

        // Enviado pero sin confirmar entrega: se deja pasar solo si el operador
        // lo asume, porque el cliente todavia puede rechazar el paquete.
        if ($order->status === 'shipped' && !request()->boolean('allow_undelivered')) {
            return response()->json([
                'error'   => 'El pedido esta ENVIADO pero Saga aun no confirma la entrega. '
                           . 'Si el cliente lo rechaza, la boleta quedaria emitida y habria que anularla '
                           . 'con nota de credito. Confirma que quieres emitirla igual.',
                'confirm' => 'allow_undelivered',
            ], 422);
        }

        if ($order->status !== 'delivered' && $order->status !== 'shipped') {
            return response()->json([
                'error' => 'Estado no apto para facturar: ' . $order->status,
            ], 422);
        }

        // Guard anti-duplicado: si Saga YA tiene un comprobante de este pedido
        // (emitido en otro sistema), no generamos otra boleta. Lo marcamos cumplido.
        $service = MarketplaceOrchestrator::resolveService($channel);
        if ($service && method_exists($service, 'getOrderInvoiceNumber')) {
            try {
                $existing = $service->getOrderInvoiceNumber($order->external_order_id);
            } catch (\Throwable $e) {
                $existing = null; // best-effort; no bloquea
            }
            if ($existing) {
                $order->update([
                    'invoice_uploaded_at' => $order->invoice_uploaded_at ?? now(),
                    'invoice_upload_error' => 'Boleta externa en Saga: ' . $existing,
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Este pedido ya tiene boleta en Saga (' . $existing . '). Se marcó como cumplido; no se generó otra.',
                ]);
            }
        }

        try {
            $document = app(MarketplaceInvoiceService::class)->emitInvoice($order);
            return response()->json([
                'success' => true,
                'message' => 'Boleta generada: ' . $document->number_full,
                'number'  => $document->number_full,
            ]);
        } catch (\Throwable $e) {
            \Log::channel('payments')->error('Saga: error al generar boleta', [
                'marketplace_order_id' => $orderId, 'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Marca manualmente un pedido como "boleta ya emitida" (en otro sistema),
     * sin generar comprobante en EBAEMY. Para pedidos ya facturados por fuera.
     */
    public function markInvoiced(int $channelId, int $orderId)
    {
        $order = MarketplaceOrder::where('channel_id', $channelId)->findOrFail($orderId);
        $order->update([
            'invoice_uploaded_at' => $order->invoice_uploaded_at ?? now(),
            'invoice_upload_error' => $order->invoice_upload_error ?: 'Boleta emitida fuera de EBAEMY (marcado manual)',
        ]);
        return response()->json(['success' => true, 'message' => 'Pedido marcado: ya tiene boleta (externa).']);
    }

    /**
     * Sube a Saga (SetInvoicePDF) la boleta ya emitida del pedido.
     */
    public function uploadInvoice(int $channelId, int $orderId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);
        $order = MarketplaceOrder::where('channel_id', $channelId)->findOrFail($orderId);
        $service = MarketplaceOrchestrator::resolveService($channel);

        if (!$service || !method_exists($service, 'setInvoicePDF')) {
            return response()->json(['error' => 'Acción no disponible para este canal'], 400);
        }

        // Idempotencia: ya subida.
        if ($order->invoice_uploaded_at) {
            return response()->json(['success' => true, 'message' => 'La boleta ya estaba subida a Saga']);
        }

        if (!$order->document_id) {
            return response()->json(['error' => 'Genera la boleta antes de subirla a Saga.'], 422);
        }
        $document = Document::find($order->document_id);
        if (!$document) {
            return response()->json(['error' => 'No se encontró el comprobante emitido.'], 422);
        }

        [$ids] = $this->dispatchParams($order, $service);
        if (empty($ids)) {
            return response()->json(['error' => 'La orden no tiene items'], 422);
        }

        try {
            $pdfBase64 = base64_encode($this->getStorage($document->filename, 'pdf'));
            $service->setInvoicePDF(
                $ids,
                $document->number_full,
                $document->date_of_issue->format('Y-m-d'),
                $pdfBase64
            );
            $order->update(['invoice_uploaded_at' => now(), 'invoice_upload_error' => null]);
            \Log::channel('payments')->info('Saga: boleta subida', [
                'marketplace_order_id' => $orderId, 'number' => $document->number_full,
            ]);
            return response()->json(['success' => true, 'message' => 'Boleta subida a Saga: ' . $document->number_full]);
        } catch (\Throwable $e) {
            $order->update(['invoice_upload_error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Orders ─────────────────────────────────────────────────

    public function orders(Request $request)
    {
        // El panel de despacho filtra por estado en el cliente (pestañas) y necesita
        // todos los pedidos, no 20. Tope alto para no traer históricos infinitos.
        // Carga el Order enlazado (solo campos de comprobante) para que el flujo
        // guiado sepa si el tenant ya generó la boleta de cada pedido.
        $orders = MarketplaceOrder::with([
                'channel:id,platform,name',
                'order:id,number_document,document_external_id',
            ])
            ->when($request->channel_id, fn($q) => $q->where('channel_id', $request->channel_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        return response()->json($orders);
    }

    // ── Logs ───────────────────────────────────────────────────

    public function logs(Request $request)
    {
        $logs = MarketplaceSyncLog::with('channel:id,platform,name')
            ->when($request->channel_id, fn($q) => $q->where('channel_id', $request->channel_id))
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($logs);
    }

    // ── Meta Feed ──────────────────────────────────────────────

    public function generateFeed(int $channelId)
    {
        $channel = MarketplaceChannel::findOrFail($channelId);

        if ($channel->platform !== 'meta') {
            return response()->json(['error' => 'Feed only available for Meta channels'], 400);
        }

        $service = new \App\Services\Marketplace\MetaFeedService($channel);
        $service->generateXmlFeed();
        $service->generateCsvFeed();

        return response()->json([
            'success' => true,
            'feeds' => [
                'xml' => asset('storage/feeds/meta-catalog.xml'),
                'csv' => asset('storage/feeds/meta-catalog.csv'),
            ],
        ]);
    }
}
