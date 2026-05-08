<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemOption;
use App\Models\Tenant\ItemOptionValue;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\ItemVariantWarehouse;
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

    public function index(Item $item): JsonResponse
    {
        $item->load([
            'itemOptions.values',
            'variants.optionValues',
            'variants.warehouseStocks.warehouse',
        ]);

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
            'variants' => $item->variants->map(fn($v) => $this->formatVariant($v)),
        ]);
    }

    // ────────────────────────────────────────────────────────────────────────
    // POST /items/{item}/variants/options
    // Guarda opciones + valores y genera variantes en un solo paso.
    // ────────────────────────────────────────────────────────────────────────

    public function saveOptions(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'options'                       => 'required|array|min:1|max:5',
            'options.*.name'                => 'required|string|max:80',
            'options.*.position'            => 'integer|min:0',
            'options.*.values'              => 'required|array|min:1',
            'options.*.values.*.value'      => 'required|string|max:100',
            'options.*.values.*.color_hex'  => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'options.*.values.*.position'   => 'integer|min:0',
        ]);

        DB::connection('tenant')->transaction(function () use ($data, $item) {
            // Borrar opciones anteriores (cascade borra valores y el pivot)
            // Las variantes se sincronizan después
            $item->itemOptions()->delete();

            foreach ($data['options'] as $pos => $optData) {
                $option = $item->itemOptions()->create([
                    'name'     => $optData['name'],
                    'position' => $optData['position'] ?? $pos,
                ]);

                foreach ($optData['values'] as $vPos => $vData) {
                    $option->values()->create([
                        'value'     => $vData['value'],
                        'color_hex' => $vData['color_hex'] ?? null,
                        'position'  => $vData['position'] ?? $vPos,
                    ]);
                }
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
            'sku'                => 'nullable|string|max:100',
            'barcode'            => 'nullable|string|max:100',
            'sale_unit_price'    => 'nullable|numeric|min:0',
            'purchase_unit_price'=> 'nullable|numeric|min:0',
            'display_name'       => 'nullable|string|max:255',
            'is_active'          => 'boolean',
        ]);

        // Solo persistimos los campos efectivamente enviados — distinguimos
        // "no enviado" (no tocar) de "enviado como null" (limpiar). El frontend
        // manda sale_unit_price=null cuando el seller no puso precio para que
        // la variante herede el del producto padre; el array_filter anterior
        // descartaba el null y dejaba el precio viejo, anulando esa lógica.
        $update = [];
        foreach (['sku', 'barcode', 'sale_unit_price', 'purchase_unit_price', 'display_name', 'is_active'] as $field) {
            if ($request->has($field)) {
                $update[$field] = $data[$field] ?? null;
            }
        }
        if (!empty($update)) {
            $variant->update($update);
        }

        return response()->json([
            'success' => true,
            'variant' => $this->formatVariant($variant->fresh(['optionValues', 'warehouseStocks'])),
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
            'file' => 'required|file|mimes:jpeg,png,jpg,webp,bmp|max:15360',
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
    // POST /items/{item}/variants/{variant}/stock
    // Ajuste manual de stock físico por almacén.
    // ────────────────────────────────────────────────────────────────────────

    public function updateStock(Request $request, Item $item, ItemVariant $variant): JsonResponse
    {
        abort_if($variant->item_id !== $item->id, 404);

        $data = $request->validate([
            'warehouse_id' => 'required|integer',
            'stock'        => 'required|numeric|min:0',
        ]);

        $this->service->updateVariantStock($variant, $data['warehouse_id'], $data['stock']);

        $variant->load('warehouseStocks');

        return response()->json([
            'success' => true,
            'variant' => $this->formatVariant($variant),
        ]);
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
