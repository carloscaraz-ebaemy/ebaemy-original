<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\DiscountRule;
use App\Models\Tenant\Item;
use App\Models\Tenant\SalesChannel;
use Illuminate\Http\Request;
use App\Services\Tenant\Pricing\PriceCalculator;
use Modules\Item\Models\Category;

/**
 * CRUD para reglas de descuento automático (DiscountRule).
 *
 * Accesible desde el panel de administración del tenant.
 * Rutas:
 *   GET  /discount-rules                → index (Vue SPA)
 *   GET  /discount-rules/records        → lista paginada (JSON)
 *   GET  /discount-rules/tables         → datos para select/combos
 *   POST /discount-rules                → crear/actualizar
 *   DELETE /discount-rules/{id}         → eliminar
 *   POST /discount-rules/{id}/toggle    → activar/desactivar
 */
class DiscountRuleController extends Controller
{
    public function index()
    {
        return view('tenant.discount_rules.index');
    }

    public function records(Request $request)
    {
        $query = DiscountRule::query()
            ->with(['applyItem:id,description,internal_id', 'applyCategory:id,name'])
            ->orderByDesc('priority')
            ->orderByDesc('id');

        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $paginator = $query->paginate(config('tenant.items_per_page'));

        // Expone apply_item / apply_category_name en forma plana para el front
        $paginator->getCollection()->transform(function ($r) {
            $r->apply_item = $r->applyItem ? [
                'id'          => $r->applyItem->id,
                'description' => $r->applyItem->description,
                'internal_id' => $r->applyItem->internal_id,
            ] : null;
            $r->apply_category_name = $r->applyCategory?->name;
            unset($r->applyItem, $r->applyCategory);
            return $r;
        });

        return response()->json($paginator);
    }

    public function tables()
    {
        return response()->json([
            'channels'   => SalesChannel::active()->get(['id', 'name', 'type', 'code']),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'types'      => [
                ['id' => 'volume',      'label' => 'Descuento por volumen'],
                ['id' => 'auto',        'label' => 'Descuento automático (monto mínimo)'],
                ['id' => 'channel',     'label' => 'Descuento por canal'],
                ['id' => 'flash_sale',  'label' => 'Flash Sale (tiempo limitado)'],
                ['id' => 'bundle',      'label' => 'Descuento por pack/bundle'],
                ['id' => 'nxm',         'label' => 'Lleva N, paga M (2x1, 3x2)'],
                ['id' => 'second_unit', 'label' => 'Segunda unidad con descuento'],
                ['id' => 'progressive', 'label' => 'Descuentos progresivos (por tramos)'],
            ],
        ]);
    }

    /**
     * Búsqueda remota de items para los selectores de scope (applies_to = item / bundle).
     * Retorna hasta 20 resultados filtrados por descripción o internal_id.
     */
    public function searchItems(Request $request)
    {
        $q    = trim((string) $request->input('q', ''));
        $mode = $request->input('mode'); // 'bundle' para restringir a is_set=true

        $query = Item::query()->where(function ($w) use ($q) {
            if ($q !== '') {
                $w->where('description', 'like', "%{$q}%")
                  ->orWhere('internal_id', 'like', "%{$q}%");
            }
        })->limit(20);

        if ($mode === 'bundle') {
            $query->where('is_set', true);
        }

        return response()->json([
            'data' => $query->get(['id', 'description', 'internal_id', 'is_set'])
        ]);
    }


    /**
     * ¿Este descuento dejaría productos vendiéndose bajo costo?
     *
     * El guardarraíl de precios (`MinMarginRule`) actúa al guardar el PRODUCTO:
     * una regla de descuento puede llevar el precio final por debajo del costo
     * sin pasar por él. Las ofertas flash ya comprueban el margen antes de
     * publicarse; las reglas no lo hacían.
     *
     * Se mira aquí —al crear o editar la regla— y no en el carrito a propósito:
     * recortar el descuento en mitad de una compra le mostraría al comprador un
     * ahorro distinto del prometido, y rechazar la venta es peor. El momento de
     * avisar es cuando se define la regla.
     *
     * Respeta la decisión que el tenant ya tomó en la configuración de precios:
     * si `block_sales_below_cost` está activo, se rechaza; si no, se deja pasar.
     * No se inventa una política nueva.
     *
     * @return array{0: bool, 1: string} [se_puede, motivo]
     */
    private function revisarMargen(Request $request): array
    {
        $tipo  = (string) $request->input('discount_type');
        $valor = (float) $request->input('discount_value', 0);

        if ($valor <= 0 || !in_array($tipo, ['percentage', 'fixed'], true)) {
            return [true, ''];
        }

        $settings = \App\Models\Tenant\PricingSettings::find(1);
        if (!$settings || !$settings->block_sales_below_cost) {
            return [true, ''];
        }

        // Ámbito de la regla. `all` mira todo el catálogo con costo cargado; el
        // resto se acota, que es lo que hace el motor al aplicarla.
        $query = \App\Models\Tenant\Item::query()
            ->where('purchase_unit_price', '>', 0)
            ->where('sale_unit_price', '>', 0);

        switch ((string) $request->input('applies_to', 'all')) {
            case 'item':
            case 'bundle':
                $query->where('id', (int) $request->input('apply_item_id'));
                break;
            case 'category':
                $query->where('category_id', (int) $request->input('apply_category_id'));
                break;
        }

        $bajoCosto = 0;
        $ejemplo   = null;

        $query->select(['id', 'description', 'purchase_unit_price', 'sale_unit_price',
                        'landed_cost_extra_pct', 'liquidation_mode', 'has_variants'])
            ->chunkById(300, function ($items) use ($tipo, $valor, &$bajoCosto, &$ejemplo) {
                foreach ($items as $item) {
                    // El producto marcado para liquidar puede ir bajo costo a
                    // propósito: es su razón de ser.
                    if ($item->liquidation_mode) {
                        continue;
                    }

                    $extraPct = (float) ($item->landed_cost_extra_pct ?? 0);

                    // Cada combinación precio/costo que este producto pone en la
                    // calle. Antes solo se miraba la del padre: un producto con
                    // padre a S/ 300 y talla 45 con precio propio S/ 180 sobre un
                    // costo de S/ 155 pasaba el filtro sin que nadie lo mirara,
                    // porque la consulta nunca tocaba item_variants.
                    foreach ($this->combinacionesDePrecio($item) as [$etiqueta, $precio, $costoBase]) {
                        if ($precio <= 0) continue;

                        $costo = PriceCalculator::effectiveCost((float) $costoBase, $extraPct);

                        $final = $tipo === 'percentage'
                            ? PriceCalculator::finalPrice($precio, min($valor, 100))
                            : max(0, $precio - $valor);

                        if ($final < $costo) {
                            $bajoCosto++;
                            $ejemplo = $ejemplo ?: $etiqueta;
                        }
                    }
                }
            });

        if ($bajoCosto === 0) {
            return [true, ''];
        }

        // "Artículo" y no "producto": con variantes, lo que cae bajo costo es una
        // talla concreta, y decir "1 producto" cuando son 3 de 12 tallas del mismo
        // modelo haría buscar en el sitio equivocado. El ejemplo nombra la variante.
        return [false, sprintf(
            'Este descuento dejaría %d artículo%s vendiéndose bajo costo (por ejemplo «%s»). '
            . 'Baja el descuento, acota la regla a otra categoría, o activa el modo liquidación '
            . 'en los productos que sí quieras vender con pérdida.',
            $bajoCosto,
            $bajoCosto === 1 ? '' : 's',
            (string) $ejemplo
        )];
    }

    /**
     * Las combinaciones precio/costo que un producto expone a la venta.
     *
     * Un producto simple expone una: la suya. Uno con variantes expone una por
     * variante activa, porque cada talla puede tener precio y costo propios —y
     * cuando los tiene a null hereda los del padre, igual que en el resto del
     * sistema (ver ItemVariant::getEffectiveSalePrice).
     *
     * @return array<int, array{0: string, 1: float, 2: float}> [etiqueta, precio, costo]
     */
    private function combinacionesDePrecio($item): array
    {
        $precioPadre = (float) $item->sale_unit_price;
        $costoPadre  = (float) $item->purchase_unit_price;

        if (empty($item->has_variants)) {
            return [[(string) $item->description, $precioPadre, $costoPadre]];
        }

        $variantes = \App\Models\Tenant\ItemVariant::where('item_id', $item->id)
            ->where('is_active', true)
            ->get(['id', 'display_name', 'sale_unit_price', 'purchase_unit_price']);

        // has_variants=true sin variantes activas: se comporta como simple, que
        // es lo mismo que hace el sync del marketplace ante este caso.
        if ($variantes->isEmpty()) {
            return [[(string) $item->description, $precioPadre, $costoPadre]];
        }

        return $variantes->map(fn ($v) => [
            trim($item->description . ' · ' . ($v->display_name ?: ('variante #' . $v->id))),
            $v->sale_unit_price !== null ? (float) $v->sale_unit_price : $precioPadre,
            $v->purchase_unit_price !== null ? (float) $v->purchase_unit_price : $costoPadre,
        ])->all();
    }

    public function store(Request $request)
    {
        // FIX BUG #2: limitar discount_value según tipo
        $maxDiscount = $request->discount_type === 'percentage' ? 100 : 99999;

        // Tipos por unidad/tramo (nxm/second_unit/progressive) no usan discount_value
        // simple: sus parámetros viven en trigger_json. Se relaja la exigencia.
        $unitTypes = in_array($request->type, ['nxm', 'second_unit', 'progressive'], true);

        $request->validate([
            'name'           => 'required|string|max:100',
            'type'           => 'required|in:volume,auto,channel,flash_sale,bundle,nxm,second_unit,progressive',
            'discount_type'  => 'nullable|in:percentage,fixed',
            'discount_value' => ($unitTypes ? 'nullable' : 'required') . "|numeric|min:0|max:{$maxDiscount}",
            'applies_to'     => 'required|in:all,item,bundle,category',
            'priority'       => 'integer|min:0|max:999',
            'max_uses'       => 'nullable|integer|min:0',
            // FIX BUG #5: validar rango de fechas
            'starts_at'      => 'nullable|date',
            'ends_at'        => 'nullable|date|after_or_equal:starts_at',
        ]);

        [$ok, $motivo] = $this->revisarMargen($request);
        if (!$ok) {
            return response()->json(['success' => false, 'message' => $motivo], 422);
        }

        $data = $request->only([
            'name', 'type', 'trigger_json', 'discount_type', 'discount_value',
            'applies_to', 'apply_item_id', 'apply_category_id',
            'channel_id', 'max_uses',
            'starts_at', 'ends_at', 'is_active', 'priority', 'stackable',
        ]);

        // Limpia referencias sueltas según el scope para no dejar datos zombies
        switch ($data['applies_to'] ?? 'all') {
            case 'all':
                $data['apply_item_id'] = null;
                $data['apply_category_id'] = null;
                break;
            case 'item':
            case 'bundle':
                $data['apply_category_id'] = null;
                break;
            case 'category':
                $data['apply_item_id'] = null;
                break;
        }

        if (isset($data['trigger_json']) && is_string($data['trigger_json'])) {
            $data['trigger_json'] = json_decode($data['trigger_json'], true);
        }

        if ($request->id) {
            $rule = DiscountRule::findOrFail($request->id);
            $rule->update($data);
            $msg = 'Regla actualizada';
        } else {
            $rule = DiscountRule::create($data);
            $msg = 'Regla creada';
        }

        return response()->json(['success' => true, 'message' => $msg, 'rule' => $rule]);
    }

    public function destroy($id)
    {
        DiscountRule::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Regla eliminada']);
    }

    public function toggle($id)
    {
        $rule = DiscountRule::findOrFail($id);
        $rule->is_active = !$rule->is_active;
        $rule->save();

        $state = $rule->is_active ? 'activada' : 'desactivada';
        return response()->json(['success' => true, 'message' => "Regla {$state}", 'is_active' => $rule->is_active]);
    }
}
