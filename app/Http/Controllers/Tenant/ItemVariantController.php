<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemOption;
use App\Models\Tenant\ItemOptionValue;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\ItemVariantWarehouse;
use App\Services\Tenant\ItemVariantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $variant->update(array_filter($data, fn($v) => !is_null($v)));

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

        $result = $this->service->deleteVariant($variant);

        return response()->json(['success' => true, 'result' => $result]);
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
            'stock'               => $variant->stock,
            'variant_hash'        => $variant->variant_hash,
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
