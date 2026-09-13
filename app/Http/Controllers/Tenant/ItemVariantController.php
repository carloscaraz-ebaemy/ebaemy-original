<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemOption;
use App\Models\Tenant\ItemOptionValue;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\ItemVariantWarehouse;
use App\Rules\MinMarginRule;
use App\Services\Tenant\ItemVariantService;
use App\Services\Tenant\ImageProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * ItemVariantController
 *
 * Endpoints REST para gestionar variantes de producto.
 * Todas las rutas viven bajo /items/{item}/variants/*
 *
 * GET    /items/{item}/variants             → index (lista opciones + variantes)
 * POST   /items/{item}/variants/options     → saveOptions (guarda opciones y genera)
 * POST   /items/{item}/variants/generate    → generate (genera/sincroniza combinaciones)
 * PATCH  /items/{item}/variants/{variant}   → update (precio, SKU, imagen de variante)
 * DELETE /items/{item}/variants/{variant}   → destroy (elimina o desactiva)
 * POST   /items/{item}/variants/{variant}/stock → updateStock (ajuste de stock)
 */
class ItemVariantController extends Controller
{
    public function __construct(private ItemVariantService $service) {}

    // ────────────────────────────────────────────────────────────────────────
    // GET /items/{item}/variants
    // ────────────────────────────────────────────────────────────────────────

    public function index(Request $request, Item $item): JsonResponse
    {
        // ?include_inactive=1 → también las variantes desactivadas. Una variante
        // con stock nunca se borra, se desactiva; sin esto quedaban invisibles
        // en toda la UI y su stock era irrecuperable. Ver Item::allVariants().
        $includeInactive = filter_var(
            $request->query('include_inactive', false),
            FILTER_VALIDATE_BOOLEAN
        );
        $relation = $includeInactive ? 'allVariants' : 'variants';

        $item->load([
            'itemOptions.values',
            $relation . '.optionValues',
            $relation . '.warehouseStocks.warehouse',
        ]);

        $variants = $item->{$relation};

        return response()->json([
            'has_variants' => (bool) $item->has_variants,
            'options'      => $item->itemOptions->map(fn($opt) => [
                'id'       => $opt->id,
                'name'     => $opt->name,
                'position' => $opt->position,
                'values'   => $opt->values->map(fn($v) => [
                    'id'        => $v->id,
                    'value'     => $v->value,
                    'color_hex' => $v->color_hex,
                    'position'  => $v->position,
                ]),
            ]),
            'variants' => $variants->map(fn($v) => $this->formatVariant($v)),
            // Cuántas hay desactivadas, se pidan o no: así la pestaña puede
            // ofrecer el interruptor "ver desactivadas" solo cuando hay algo
            // que ver, en vez de mostrarlo siempre vacío.
            'inactive_count' => $item->allVariants()->where('is_active', false)->count(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /items/{item}/variants/options
    // Guarda opciones + valores y genera variantes en un solo paso.
    // ────────────────────────────────────────────────────────────────────────

    public function saveOptions(Request $request, Item $item): JsonResponse
    {
        // 'present' (no 'required|min:1') permite enviar lista VACÍA para
        // eliminar todas las variantes y volver el producto a "simple":
        // syncVariants() detecta opciones vacías → desactiva variantes y pone
        // has_variants=false. Las reglas por-opción solo aplican cuando hay
        // opciones, así que no estorban al caso vacío.
        $data = $request->validate([
            'options'                       => 'present|array|max:5',
            'options.*.id'                  => 'nullable|integer',
            'options.*.name'                => 'required|string|max:80',
            'options.*.position'            => 'integer|min:0',
            'options.*.values'              => 'required|array|min:1',
            'options.*.values.*.id'         => 'nullable|integer',
            'options.*.values.*.value'      => 'required|string|max:100',
            'options.*.values.*.color_hex'  => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'options.*.values.*.position'   => 'integer|min:0',
        ]);

        DB::connection('tenant')->transaction(function () use ($data, $item) {
            // Sincronización IN-PLACE preservando los IDs de opciones y valores.
            //
            // CRÍTICO: el variant_hash se calcula como md5 de los IDs ordenados
            // de item_option_values. Si borráramos y recreáramos los valores
            // (como hacía antes con itemOptions()->delete()), sus IDs cambiaban
            // y TODAS las variantes existentes quedaban "obsoletas" en
            // syncVariants() → se desactivaban y se recreaban vacías (sin
            // imagen, stock 0, ocultas en marketplace). Al reusar los IDs de
            // los valores que no cambian, las combinaciones sin cambios
            // conservan su hash y syncVariants() las respeta con su imagen,
            // stock y precio. Solo los valores realmente nuevos reciben IDs
            // nuevos → solo esas combinaciones se crean como variantes vacías.
            $existingOptions = $item->itemOptions()->with('values')->get();

            $keptOptionIds = [];
            foreach ($data['options'] as $pos => $optData) {
                // Match de la opción: por id si vino del front, si no por nombre
                // (case-insensitive). Reusar id sobrevive incluso a renombrar.
                $option = null;
                if (!empty($optData['id'])) {
                    $option = $existingOptions->firstWhere('id', (int) $optData['id']);
                }
                if (!$option) {
                    $option = $existingOptions->first(fn ($o) =>
                        mb_strtolower(trim($o->name)) === mb_strtolower(trim($optData['name']))
                    );
                }

                if ($option) {
                    $option->update([
                        'name'     => $optData['name'],
                        'position' => $optData['position'] ?? $pos,
                    ]);
                } else {
                    $option = $item->itemOptions()->create([
                        'name'     => $optData['name'],
                        'position' => $optData['position'] ?? $pos,
                    ]);
                    $option->setRelation('values', collect());
                }
                $keptOptionIds[] = $option->id;

                $existingValues = $option->values;
                $keptValueIds = [];
                foreach ($optData['values'] as $vPos => $vData) {
                    $value = null;
                    if (!empty($vData['id'])) {
                        $value = $existingValues->firstWhere('id', (int) $vData['id']);
                    }
                    if (!$value) {
                        $value = $existingValues->first(fn ($v) =>
                            mb_strtolower(trim($v->value)) === mb_strtolower(trim($vData['value']))
                        );
                    }

                    $attrs = [
                        'value'     => $vData['value'],
                        'color_hex' => $vData['color_hex'] ?? null,
                        'position'  => $vData['position'] ?? $vPos,
                    ];
                    if ($value) {
                        $value->update($attrs);
                    } else {
                        $value = $option->values()->create($attrs);
                    }
                    $keptValueIds[] = $value->id;
                }

                // Valores que el usuario quitó de esta opción: borrarlos (el FK
                // cascade limpia el pivot; sus combinaciones quedan obsoletas y
                // syncVariants() las desactiva/borra según tengan stock).
                $option->values()->whereNotIn('id', $keptValueIds)->delete();
            }

            // Opciones eliminadas por completo (cascade borra valores + pivot).
            // Si no quedó ninguna ($data['options'] vacío), borrar todas: el
            // producto vuelve a "simple" y syncVariants() desactiva variantes.
            if (empty($keptOptionIds)) {
                $item->itemOptions()->delete();
            } else {
                $item->itemOptions()->whereNotIn('id', $keptOptionIds)->delete();
            }
        });

        // Sincronizar variantes (crea nuevas, desactiva obsoletas)
        $stats = $this->service->syncVariants($item->fresh());

        $item->load(['itemOptions.values', 'variants.optionValues', 'variants.warehouseStocks']);

        return response()->json([
            'success'  => true,
            'stats'    => $stats,
            'options'  => $item->itemOptions,
            'variants' => $item->variants->map(fn($v) => $this->formatVariant($v)),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /items/{item}/variants/generate
    // Regenera/sincroniza combinaciones sin tocar las opciones.
    // ────────────────────────────────────────────────────────────────────────

    public function generate(Item $item): JsonResponse
    {
        $stats = $this->service->syncVariants($item);

        $item->load(['variants.optionValues', 'variants.warehouseStocks']);

        return response()->json([
            'success'  => true,
            'stats'    => $stats,
            'variants' => $item->variants->map(fn($v) => $this->formatVariant($v)),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // PATCH /items/{item}/variants/{variant}
    // Actualiza precio, SKU, imagen o display_name de una variante.
    // ────────────────────────────────────────────────────────────────────────

    public function update(Request $request, Item $item, ItemVariant $variant): JsonResponse
    {
        abort_if($variant->item_id !== $item->id, 404);

        $data = $request->validate([
            // Unicidad dentro del producto. La columna solo tenía índice, no
            // restricción, así que dos tallas podían compartir SKU y al
            // escanearlo el sistema no sabía cuál era. Se valida por producto y
            // no globalmente: el índice único en BD llegaría con la limpieza de
            // los duplicados que ya existan en los tenants.
            'sku' => [
                'nullable', 'string', 'max:100',
                Rule::unique('tenant.item_variants', 'sku')
                    ->where('item_id', $item->id)
                    ->ignore($variant->id),
            ],
            'barcode' => [
                'nullable', 'string', 'max:100',
                Rule::unique('tenant.item_variants', 'barcode')
                    ->where('item_id', $item->id)
                    ->ignore($variant->id),
            ],
            'sale_unit_price'    => 'nullable|numeric|min:0',
            'purchase_unit_price'=> 'nullable|numeric|min:0',
            'display_name'       => 'nullable|string|max:255',
            'is_active'          => 'boolean',
            // Campos con herencia (migración 2026_09_11_000002). null = hereda
            // del producto, así que 'nullable' no es laxitud: es el mecanismo.
            'compare_at_price'   => 'nullable|numeric|min:0',
            'compare_at_from'    => 'nullable|date',
            'compare_at_until'   => 'nullable|date|after_or_equal:compare_at_from',
            'stock_min'          => 'nullable|numeric|min:0',
            'min_margin_pct'     => 'nullable|numeric|between:0,99.99',
            'weight'             => 'nullable|numeric|min:0',
            'length'             => 'nullable|numeric|min:0',
            'width'              => 'nullable|numeric|min:0',
            'height'             => 'nullable|numeric|min:0',
        ]);

        // Solo persistimos los campos efectivamente enviados — distinguimos
        // "no enviado" (no tocar) de "enviado como null" (limpiar). El frontend
        // manda sale_unit_price=null cuando el seller no puso precio para que
        // la variante herede el del producto padre; el array_filter anterior
        // descartaba el null y dejaba el precio viejo, anulando esa lógica.
        $update = [];
        $campos = [
            'sku', 'barcode', 'sale_unit_price', 'purchase_unit_price', 'display_name', 'is_active',
            'compare_at_price', 'compare_at_from', 'compare_at_until',
            'stock_min', 'min_margin_pct', 'weight', 'length', 'width', 'height',
        ];
        foreach ($campos as $field) {
            if ($request->has($field)) {
                $update[$field] = $data[$field] ?? null;
            }
        }

        // Guardarraíl de margen. El precio del producto padre se valida en
        // ItemRequest con MinMarginRule; el de la variante no se validaba en
        // absoluto ('numeric|min:0' y nada más), así que con la política de
        // bloqueo activa se podía dejar una talla bajo costo por esta puerta.
        //
        // Se evalúa el precio EFECTIVO: si la variante no tiene precio propio
        // hereda el del padre, que ya pasó por la regla al guardarse el producto.
        // Y contra el costo efectivo que quedará tras esta misma petición, no
        // contra el viejo, porque precio y costo pueden venir juntos.
        $nuevoCosto  = array_key_exists('purchase_unit_price', $update)
            ? ($update['purchase_unit_price'] === null ? null : (float) $update['purchase_unit_price'])
            : ($variant->purchase_unit_price === null ? null : (float) $variant->purchase_unit_price);

        $nuevoPrecio = array_key_exists('sale_unit_price', $update)
            ? $update['sale_unit_price']
            : $variant->sale_unit_price;

        $precioEfectivo = $nuevoPrecio !== null
            ? (float) $nuevoPrecio
            : (float) $item->sale_unit_price;

        // Solo cuando la petición toca de verdad el precio o el costo. Si el PATCH
        // viene únicamente a desactivar la variante o a corregirle el SKU, no hay
        // nada que validar — y bloquearlo impediría desactivar precisamente la
        // variante mal valorada, que es lo que uno quiere hacer al descubrirla.
        $tocaPrecio = array_key_exists('sale_unit_price', $update)
                   || array_key_exists('purchase_unit_price', $update);

        if ($tocaPrecio && $precioEfectivo > 0) {
            // Se valida contra el estado que la variante TENDRÁ: si esta misma
            // petición trae un min_margin_pct propio, es ese el que rige, no el
            // heredado del padre.
            $variantParaRegla = clone $variant;
            if (array_key_exists('min_margin_pct', $update)) {
                $variantParaRegla->min_margin_pct = $update['min_margin_pct'];
            }

            $rule = MinMarginRule::forVariant($variantParaRegla, $item, $nuevoCosto);

            if (!$rule->passes('sale_unit_price', $precioEfectivo)) {
                return response()->json([
                    'success' => false,
                    'message' => sprintf('%s — %s', $variant->display_name ?: 'Variante', $rule->message()),
                    'errors'  => ['sale_unit_price' => [$rule->message()]],
                ], 422);
            }
        }

        if (!empty($update)) {
            $variant->update($update);

            // Propagar al marketplace en el momento. Las imágenes ya tenían este
            // disparador desde el principio; el PRECIO y el estado no, así que un
            // cambio de precio tardaba hasta 30 minutos en llegar a ebaemy.com y
            // durante ese rato se vendía al precio viejo. Importa más el precio
            // que la foto.
            $this->triggerMarketplaceSync($item);
        }

        return response()->json([
            'success' => true,
            'variant' => $this->formatVariant($variant->fresh(['optionValues', 'warehouseStocks'])),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // PATCH /items/{item}/variants/bulk
    // Una operación declarativa sobre varias variantes, en una transacción.
    // ────────────────────────────────────────────────────────────────────────

    public function bulkUpdate(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'op'          => 'required|in:set_price,adjust_pct,set_cost,activate,deactivate,clear_price',
            'value'       => 'nullable|numeric',
            // Vacío = todas las variantes activas del producto.
            'variant_ids' => 'nullable|array',
            'variant_ids.*' => 'integer',
        ]);

        $query = ItemVariant::where('item_id', $item->id);
        if (!empty($data['variant_ids'])) {
            $query->whereIn('id', $data['variant_ids']);
        } else {
            $query->where('is_active', true);
        }

        $variants = $query->get();

        if ($variants->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay variantes sobre las que aplicar el cambio.',
            ], 422);
        }

        // `value` es opcional: clear_price, activate y deactivate no lo necesitan
        // y el cliente no lo manda. Leerlo con $data['value'] reventaba con
        // "Undefined array key" —un 500— en esas tres operaciones.
        $valor = isset($data['value']) ? (float) $data['value'] : null;

        if (in_array($data['op'], ['set_price', 'adjust_pct', 'set_cost'], true) && $valor === null) {
            return response()->json([
                'success' => false,
                'message' => 'Falta el valor a aplicar.',
            ], 422);
        }

        // Se calcula TODO antes de escribir nada, y si una sola variante rompe el
        // guardarraíl se rechaza la operación completa. Aplicar la mitad de un
        // "subir 10 % a las 24 tallas" dejaría el producto con dos listas de
        // precios distintas y sin forma de saber dónde se cortó.
        $cambios  = [];
        $rechazos = [];

        foreach ($variants as $variant) {
            $update = [];

            switch ($data['op']) {
                case 'set_price':
                    $update['sale_unit_price'] = $valor;
                    break;

                case 'clear_price':
                    // Vuelve a heredar el precio del producto padre.
                    $update['sale_unit_price'] = null;
                    break;

                case 'adjust_pct':
                    // Sobre el precio EFECTIVO: una variante que hereda del padre
                    // también sube, y al hacerlo deja de heredar. Es lo que se
                    // espera de "subir un 10 %" y evita que unas suban y otras no.
                    $base = $variant->sale_unit_price !== null
                        ? (float) $variant->sale_unit_price
                        : (float) $item->sale_unit_price;
                    $update['sale_unit_price'] = round($base * (1 + $valor / 100), 4);
                    break;

                case 'set_cost':
                    $update['purchase_unit_price'] = $valor;
                    break;

                case 'activate':
                    $update['is_active'] = true;
                    break;

                case 'deactivate':
                    $update['is_active'] = false;
                    break;
            }

            // Mismo guardarraíl que el PATCH individual. Desactivar no se valida:
            // bloquearlo impediría retirar justo la variante mal valorada.
            if (array_key_exists('sale_unit_price', $update) || array_key_exists('purchase_unit_price', $update)) {
                $costo = array_key_exists('purchase_unit_price', $update)
                    ? $update['purchase_unit_price']
                    : ($variant->purchase_unit_price === null ? null : (float) $variant->purchase_unit_price);

                $precio = array_key_exists('sale_unit_price', $update)
                    ? $update['sale_unit_price']
                    : $variant->sale_unit_price;

                $precioEfectivo = $precio !== null ? (float) $precio : (float) $item->sale_unit_price;

                if ($precioEfectivo > 0) {
                    $rule = MinMarginRule::forVariant($variant, $item, $costo === null ? null : (float) $costo);
                    if (!$rule->passes('sale_unit_price', $precioEfectivo)) {
                        $rechazos[] = ($variant->display_name ?: ('#' . $variant->id)) . ': ' . $rule->message();
                        continue;
                    }
                }
            }

            $cambios[] = [$variant, $update];
        }

        if (!empty($rechazos)) {
            return response()->json([
                'success' => false,
                'message' => 'No se aplicó ningún cambio: ' . count($rechazos)
                           . ' variante(s) quedarían fuera de la política de precios.',
                'detalle' => $rechazos,
            ], 422);
        }

        DB::connection('tenant')->transaction(function () use ($cambios, $item) {
            foreach ($cambios as [$variant, $update]) {
                if (!empty($update)) {
                    $variant->update($update);
                }
            }

            // activate/deactivate cambian qué variantes cuentan en el total.
            $this->service->propagateStock($item->fresh());
        });

        $this->triggerMarketplaceSync($item);

        $item->load(['allVariants.optionValues', 'allVariants.warehouseStocks']);

        return response()->json([
            'success'  => true,
            'affected' => count($cambios),
            'variants' => $item->allVariants->map(fn($v) => $this->formatVariant($v)),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // DELETE /items/{item}/variants/{variant}
    // ────────────────────────────────────────────────────────────────────────

    public function destroy(Item $item, ItemVariant $variant): JsonResponse
    {
        abort_if($variant->item_id !== $item->id, 404);

        // Limpieza best-effort de la imagen de la variante. Si falla no rompe
        // el delete (la fila se borra igual y el archivo huérfano se puede
        // limpiar luego con un comando manual).
        $this->deleteVariantImageFile($variant->image);

        $result = $this->service->deleteVariant($variant);

        // Borrar o desactivar una variante cambia el rango de precio y el stock
        // publicados: el listado central debe enterarse ya, no en media hora.
        $this->triggerMarketplaceSync($item);

        return response()->json(['success' => true, 'result' => $result]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /items/{item}/variants/{variant}/image
    // Sube imagen para una variante (talla/color/etc). Reusa el mismo flujo
    // que la imagen del item padre vía ImageProcessingService — convierte
    // a webp y genera 3 tamaños (main/medium/small). Solo guardamos el
    // filename del tamaño main; los otros se sirven con sufijo desde la UI.
    // ────────────────────────────────────────────────────────────────────────

    public function uploadImage(Request $request, Item $item, ItemVariant $variant): JsonResponse
    {
        abort_if($variant->item_id !== $item->id, 404);

        $request->validate([
            // HEIC/HEIF aceptados como red de seguridad cuando el iPhone no
            // convirtió a JPG en el `accept`. El service lo convierte después.
            'file' => 'required|file|mimes:jpeg,png,jpg,webp,bmp,heic,heif|max:15360',
        ]);

        try {
            $file = $request->file('file');

            // Copiar al temp propio (el PHP upload temp puede limpiarse antes)
            $temp = tempnam(sys_get_temp_dir(), 'vimg_');
            file_put_contents($temp, file_get_contents($file->getPathname()));

            $validation = ImageProcessingService::validate($temp);
            if (!$validation['success']) {
                @unlink($temp);
                return response()->json(['success' => false, 'message' => $validation['message']], 422);
            }

            // Nombre con prefijo de variante para distinguir de imágenes del padre
            $rawName = $file->getClientOriginalName();
            $prefix  = 'v' . $variant->id . '-' . ($variant->sku ?: 'var');
            $base    = ImageProcessingService::sanitizeFilename($rawName ?: 'variant', $prefix);

            $result = ImageProcessingService::processAndStore($temp, $base);

            // Borrar imagen anterior si la había (best-effort)
            if (!empty($variant->image)) {
                $this->deleteVariantImageFile($variant->image);
            }

            $variant->update(['image' => $result['main']]);

            // Si el item está publicado en marketplace, propagar la nueva
            // imagen al índice central inmediatamente (sin esperar al cron de
            // 30 min). Sin este trigger, el seller subiría la imagen y no la
            // vería en ebaemy.com/marketplace por hasta media hora.
            $this->triggerMarketplaceSync($item);

            return response()->json([
                'success' => true,
                'image'   => $variant->image,
                'image_url' => $this->variantImageUrl($variant->image),
                'variant' => $this->formatVariant($variant->fresh(['optionValues', 'warehouseStocks'])),
            ]);
        } catch (\Throwable $e) {
            Log::error('[ItemVariantController::uploadImage] ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la imagen: ' . $e->getMessage(),
            ], 422);
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // DELETE /items/{item}/variants/{variant}/image
    // Quita la imagen específica de la variante (la variante cae al fallback
    // de la imagen del item padre en la UI). El archivo se borra del disco.
    // ────────────────────────────────────────────────────────────────────────

    public function deleteImage(Item $item, ItemVariant $variant): JsonResponse
    {
        abort_if($variant->item_id !== $item->id, 404);

        $this->deleteVariantImageFile($variant->image);
        $variant->update(['image' => null]);

        // Si está en marketplace, propagar el cambio inmediato (sin esperar cron).
        $this->triggerMarketplaceSync($item);

        return response()->json([
            'success' => true,
            'variant' => $this->formatVariant($variant->fresh(['optionValues', 'warehouseStocks'])),
        ]);
    }

    /**
     * Dispara sync inmediato del item al marketplace central, solo si está
     * publicado (marketplace_publishable=true) y activo. Best-effort: errores
     * se loguean sin propagar — el cron de 30 min eventualmente resuelve.
     */
    private function triggerMarketplaceSync(Item $item): void
    {
        try {
            if (!$item->marketplace_publishable || ($item->mp_status ?? '') === 'rejected') {
                return;
            }
            $hostname = app(\Hyn\Tenancy\Contracts\CurrentHostname::class);
            if (!$hostname) return;

            app(\App\Services\System\MarketplaceListingSyncService::class)
                ->syncItem($hostname->id, (int) $item->id);
        } catch (\Throwable $e) {
            \Log::warning('[ItemVariantController] triggerMarketplaceSync failed', [
                'item_id' => $item->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Borra los 3 archivos (main + medium + small) generados por
     * ImageProcessingService a partir del filename `main` guardado.
     * Best-effort: errores se loguean pero no se propagan.
     */
    private function deleteVariantImageFile(?string $main): void
    {
        if (empty($main)) return;

        $base = preg_replace('/\.[^.]+$/', '', $main); // sin extensión
        $disk = ImageProcessingService::disk();

        foreach ([$main, $base . '_medium.webp', $base . '_small.webp', $base . '_medium.jpg', $base . '_small.jpg'] as $f) {
            try {
                $path = ImageProcessingService::BASE_DIR . '/' . $f;
                if (Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                }
            } catch (\Throwable $e) {
                Log::warning('[ItemVariantController] cleanup file failed', ['file' => $f, 'error' => $e->getMessage()]);
            }
        }
    }

    private function variantImageUrl(?string $filename): ?string
    {
        return ImageProcessingService::getUrl($filename);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /items/{item}/use-parent-image
    // Flag por producto: cuando está activo, el marketplace ignora las
    // imágenes individuales de las variantes y usa siempre la imagen del
    // producto padre. Útil cuando el seller no tiene fotos por color.
    // ────────────────────────────────────────────────────────────────────────

    public function setUseParentImage(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'use_parent_image_for_variants' => 'required|boolean',
        ]);

        $item->update([
            'use_parent_image_for_variants' => (bool) $data['use_parent_image_for_variants'],
        ]);

        $this->triggerMarketplaceSync($item);

        return response()->json([
            'success' => true,
            'use_parent_image_for_variants' => (bool) $item->use_parent_image_for_variants,
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /items/{item}/variants/{variant}/primary
    // Marca esta variante como la "principal" — la imagen que se ve en la
    // card del marketplace por defecto. Es exclusiva por item: al marcar
    // una, las demás del mismo item quedan en false.
    // ────────────────────────────────────────────────────────────────────────

    public function setPrimary(Item $item, ItemVariant $variant): JsonResponse
    {
        abort_if($variant->item_id !== $item->id, 404);

        DB::connection('tenant')->transaction(function () use ($item, $variant) {
            ItemVariant::where('item_id', $item->id)->update(['is_primary' => false]);
            $variant->update(['is_primary' => true]);
        });

        // Propagar al system inmediatamente (sin esperar al cron) para que
        // /marketplace refleje el cambio al recargar.
        $this->triggerMarketplaceSync($item);

        return response()->json([
            'success' => true,
            'variant' => $this->formatVariant($variant->fresh(['optionValues', 'warehouseStocks'])),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /items/{item}/variants/{variant}/reactivate
    // Vuelve a poner en circulación una variante desactivada. Su stock, que
    // seguía guardado en item_variant_warehouse, vuelve a contar en el total
    // del producto (ver ItemVariantService::COUNT_INACTIVE).
    // ────────────────────────────────────────────────────────────────────────

    public function reactivate(Item $item, ItemVariant $variant): JsonResponse
    {
        abort_if($variant->item_id !== $item->id, 404);

        if ($variant->is_active) {
            return response()->json([
                'success' => true,
                'message' => 'La variante ya estaba activa.',
                'variant' => $this->formatVariant($variant->fresh(['optionValues', 'warehouseStocks'])),
            ]);
        }

        // Una variante solo se puede reactivar si su combinación sigue siendo
        // válida con las opciones actuales del producto. Si el usuario borró el
        // color "Rojo", la variante "Rojo / M" no puede volver: no hay dónde
        // elegirla, y syncVariants() la desactivaría otra vez en el próximo
        // guardado. En ese caso lo que toca es mover su stock, no reactivarla.
        $validHashes = $this->service->validHashesFor($item);
        if (!in_array($variant->variant_hash, $validHashes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta combinación ya no existe en las opciones del producto. '
                           . 'Vuelve a crear el valor que falta, o mueve su stock a otra variante.',
            ], 422);
        }

        DB::connection('tenant')->transaction(function () use ($item, $variant) {
            $variant->update(['is_active' => true]);
            $this->service->propagateStock($item->fresh());
        });

        $this->triggerMarketplaceSync($item);

        return response()->json([
            'success' => true,
            'variant' => $this->formatVariant($variant->fresh(['optionValues', 'warehouseStocks'])),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /items/{item}/variants/{variant}/move-stock
    // Traslada el stock de una variante a otra, almacén por almacén. Es la
    // salida para el stock atrapado en una combinación que ya no existe.
    // ────────────────────────────────────────────────────────────────────────

    public function moveStock(Request $request, Item $item, ItemVariant $variant): JsonResponse
    {
        abort_if($variant->item_id !== $item->id, 404);

        $data = $request->validate([
            'target_variant_id' => 'required|integer|different:' . $variant->id,
        ]);

        $target = ItemVariant::where('item_id', $item->id)
            ->where('id', (int) $data['target_variant_id'])
            ->first();

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'La variante de destino no pertenece a este producto.',
            ], 422);
        }

        if (!$target->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede mover stock a una variante desactivada.',
            ], 422);
        }

        $moved = $this->service->moveStockBetweenVariants($variant, $target);

        $this->triggerMarketplaceSync($item);

        return response()->json([
            'success' => true,
            'moved'   => $moved,
            'message' => $moved > 0
                ? sprintf('Se movieron %s unidades a "%s".', rtrim(rtrim(number_format($moved, 4, '.', ''), '0'), '.'), $target->display_name)
                : 'La variante no tenía stock que mover.',
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /items/{item}/variants/{variant}/stock
    // Ajuste manual de stock físico por almacén.
    // ────────────────────────────────────────────────────────────────────────

    public function updateStock(Request $request, Item $item, ItemVariant $variant): JsonResponse
    {
        abort_if($variant->item_id !== $item->id, 404);

        $data = $request->validate([
            'warehouse_id' => 'required|integer',
            'stock'        => 'required|numeric|min:0',
            'reason'       => 'nullable|string|max:255',
        ]);

        // Delta ANTES de escribir: el ajuste llega como valor absoluto y el
        // kardex necesita el movimiento. Sin esto el ajuste no dejaba rastro —
        // no había quién ni por qué— a diferencia de la recepción de compras,
        // que sí registra movimiento.
        $anterior = (float) (ItemVariantWarehouse::where('item_variant_id', $variant->id)
            ->where('warehouse_id', (int) $data['warehouse_id'])
            ->value('stock_physical') ?? 0);

        $delta = (float) $data['stock'] - $anterior;

        $this->service->updateVariantStock($variant, $data['warehouse_id'], $data['stock']);

        $this->logStockAdjustment($item, $variant, (int) $data['warehouse_id'], $delta, $data['reason'] ?? null);

        // Un ajuste de stock puede dejar la variante en cero —y entonces el
        // marketplace debe dejar de ofrecerla— o sacarla de cero, y entonces
        // debe volver a aparecer. Esperar al cron es vender lo que no hay.
        $this->triggerMarketplaceSync($item);

        $variant->load('warehouseStocks');

        return response()->json([
            'success' => true,
            'variant' => $this->formatVariant($variant),
        ]);
    }

    /**
     * Deja el ajuste manual de stock en el kardex de inventario.
     *
     * El observer de Inventory suma o resta item_warehouse.stock por su cuenta,
     * lo que para un producto con variantes sería doble conteo — pero
     * updateVariantStock() ya llamó a propagateStock(), que recalcula el padre
     * desde las variantes y sobrescribe ese bump. Es el mismo razonamiento que
     * documenta la recepción de órdenes de compra.
     *
     * Best-effort: si el kardex falla, el ajuste de stock ya está hecho y es
     * correcto. Perder la anotación es peor que perder el ajuste, pero no
     * justifica deshacerlo.
     */
    private function logStockAdjustment(
        Item $item,
        ItemVariant $variant,
        int $warehouseId,
        float $delta,
        ?string $reason
    ): void {
        if (abs($delta) < 0.0001) {
            return; // No hubo cambio: no se ensucia el kardex.
        }

        try {
            $quien   = optional(auth()->user())->name ?? 'sistema';
            $qué     = $variant->display_name ?: ('variante #' . $variant->id);
            $motivo  = $reason ? (' — ' . $reason) : '';
            $signo   = $delta > 0 ? '+' : '';

            \Modules\Inventory\Models\Inventory::create([
                'type'         => $delta > 0 ? 1 : 3,   // 1 entrada, 3 salida
                'description'  => 'Ajuste de variante ' . $qué . $motivo,
                'item_id'      => $item->id,
                'warehouse_id' => $warehouseId,
                'quantity'     => abs($delta),
                'comments'     => sprintf(
                    'Ajuste manual de stock (%s%s) en "%s" por %s',
                    $signo,
                    rtrim(rtrim(number_format($delta, 4, '.', ''), '0'), '.'),
                    $qué,
                    $quien
                ),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ItemVariantController] no se pudo registrar el ajuste en kardex', [
                'item_id'    => $item->id,
                'variant_id' => $variant->id,
                'delta'      => $delta,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    // ────────────────────────────────────────────────────────────────────────
    // Helpers
    // ────────────────────────────────────────────────────────────────────────

    private function formatVariant(ItemVariant $variant): array
    {
        return [
            'id'                  => $variant->id,
            'display_name'        => $variant->display_name,
            'sku'                 => $variant->sku,
            'barcode'             => $variant->barcode,
            'sale_unit_price'     => $variant->sale_unit_price,
            'purchase_unit_price' => $variant->purchase_unit_price,
            'is_active'           => $variant->is_active,
            'is_primary'          => (bool) $variant->is_primary,
            'compare_at_price'    => $variant->compare_at_price,
            'compare_at_from'     => optional($variant->compare_at_from)->format('Y-m-d'),
            'compare_at_until'    => optional($variant->compare_at_until)->format('Y-m-d'),
            'stock_min'           => $variant->stock_min,
            'min_margin_pct'      => $variant->min_margin_pct,
            'weight'              => $variant->weight,
            'length'              => $variant->length,
            'width'               => $variant->width,
            'height'              => $variant->height,
            'stock'               => $variant->stock,
            'variant_hash'        => $variant->variant_hash,
            'image'               => $variant->image,
            'image_url'           => $variant->image
                ? ImageProcessingService::getUrl($variant->image)
                : null,
            'option_values'       => $variant->relationLoaded('optionValues')
                ? $variant->optionValues->map(fn($v) => [
                    'id'            => $v->id,
                    'value'         => $v->value,
                    'color_hex'     => $v->color_hex,
                    'item_option_id'=> $v->item_option_id,
                ])
                : [],
            'warehouse_stocks' => $variant->relationLoaded('warehouseStocks')
                ? $variant->warehouseStocks->map(fn($ws) => [
                    'warehouse_id'    => $ws->warehouse_id,
                    'warehouse_name'  => optional($ws->warehouse)->description,
                    'stock_physical'  => $ws->stock_physical,
                    'stock_committed' => $ws->stock_committed,
                    'stock_available' => $ws->stock_available,
                ])
                : [],
        ];
    }
}
