<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\MarketplaceProduct;
use Illuminate\Http\Request;
use Modules\Item\Models\Brand;

/**
 * Completar productos: lo que le falta a cada producto para publicarse bien
 * en la tienda online, el marketplace de ebaemy y los canales externos
 * (Saga, Mercado Libre): marca, foto y SKU propio de cada variante.
 *
 * Una variante sin SKU hereda el código del producto, así que todas las
 * variantes de ese producto comparten el mismo SKU y un canal externo no
 * puede distinguirlas.
 */
class CatalogCompletenessController extends Controller
{
    private const SIN_FOTO = ['', 'imagen-no-disponible.jpg'];

    public function panel(Request $request)
    {
        $falta = in_array($request->input('falta'), ['marca', 'foto', 'sku', 'todos'], true)
            ? $request->input('falta')
            : 'algo';
        $q = trim((string) $request->input('q', ''));

        $base = fn () => Item::where('active', 1)->where('unit_type_id', '!=', 'ZZ');

        $sinMarca = fn ($w) => $w->whereNull('brand_id')->orWhere('brand_id', 0);
        $sinFoto  = fn ($w) => $w->whereNull('image')->orWhereIn('image', self::SIN_FOTO);
        $sinSku   = fn ($w) => $w->where('has_variants', 1)->whereHas('variants', fn ($v) => $v->where('is_active', 1)
            ->where(fn ($s) => $s->whereNull('sku')->orWhere('sku', '')));

        $items = $base()
            ->when($falta === 'marca', fn ($x) => $x->where($sinMarca))
            ->when($falta === 'foto', fn ($x) => $x->where($sinFoto))
            ->when($falta === 'sku', fn ($x) => $x->where($sinSku))
            ->when($falta === 'algo', fn ($x) => $x->where(fn ($w) => $w->where($sinMarca)->orWhere($sinFoto)->orWhere($sinSku)))
            ->when($q !== '', fn ($x) => $x->where(fn ($w) => $w->where('description', 'like', "%{$q}%")
                ->orWhere('internal_id', 'like', "%{$q}%")))
            ->with(['brand:id,name', 'variants' => fn ($v) => $v->where('is_active', 1)->orderBy('id')])
            ->orderBy('description')
            ->paginate(\App\Support\PerPage::resolve(30))
            ->withQueryString();

        $variantesSinSku = ItemVariant::where('is_active', 1)
            ->where(fn ($s) => $s->whereNull('sku')->orWhere('sku', ''))
            ->whereHas('item', fn ($i) => $i->where('active', 1)->where('has_variants', 1))
            ->count();

        return view('tenant.items.completar', [
            'items'    => $items,
            'falta'    => $falta,
            'q'        => $q,
            'marcas'   => Brand::orderBy('name')->pluck('name'),
            'resumen'  => [
                'total'      => $base()->count(),
                'sin_marca'  => $base()->where($sinMarca)->count(),
                'sin_foto'   => $base()->where($sinFoto)->count(),
                'var_sin_sku'=> $variantesSinSku,
                'incompletos'=> $base()->where(fn ($w) => $w->where($sinMarca)->orWhere($sinFoto)->orWhere($sinSku))->count(),
            ],
            'sinFoto'  => self::SIN_FOTO,
        ]);
    }

    /** Asigna la marca escrita; si no existe, la crea. */
    public function saveBrand(Request $request)
    {
        $data = $request->validate([
            'item_id' => 'required|integer',
            'marca'   => 'nullable|string|max:100',
        ]);

        $item = Item::find($data['item_id']);
        if (!$item) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        $nombre = trim(preg_replace('/\s+/', ' ', (string) $data['marca']));
        if ($nombre === '') {
            $item->update(['brand_id' => null]);

            return response()->json(['success' => true, 'complete' => false]);
        }

        // Sin distinguir mayúsculas: «SAMSUNG» y «Samsung» son la misma marca.
        $brand = Brand::whereRaw('LOWER(name) = ?', [mb_strtolower($nombre)])->first()
            ?? Brand::create(['name' => $nombre]);

        $item->update(['brand_id' => $brand->id]);

        return response()->json(['success' => true, 'complete' => true, 'marca' => $brand->name]);
    }

    /** Guarda el SKU propio de una variante. Debe ser único. */
    public function saveVariantSku(Request $request)
    {
        $data = $request->validate([
            'variant_id' => 'required|integer',
            'sku'        => 'nullable|string|max:60',
        ]);

        $variant = ItemVariant::find($data['variant_id']);
        if (!$variant) {
            return response()->json(['error' => 'Variante no encontrada.'], 404);
        }

        $sku = trim((string) $data['sku']);
        if ($sku !== '') {
            $repetida = ItemVariant::where('sku', $sku)->where('id', '!=', $variant->id)->first();
            if ($repetida) {
                $dueño = optional(Item::find($repetida->item_id))->description;

                return response()->json(['error' => "El SKU «{$sku}» ya lo usa otra variante"
                    . ($dueño ? " ({$dueño} — {$repetida->display_name})" : '') . '.'], 422);
            }
            $producto = Item::where('internal_id', $sku)->where('id', '!=', $variant->item_id)->first();
            if ($producto) {
                return response()->json(['error' => "El SKU «{$sku}» es el código del producto «{$producto->description}»."], 422);
            }
        }

        $this->conservarEnlacesDeCanal($variant, $sku);

        $variant->sku = $sku !== '' ? $sku : null;
        $variant->save();

        return response()->json(['success' => true, 'complete' => $sku !== '']);
    }

    /**
     * Un producto con variantes enlazado a un canal (Saga) publica cada
     * variante con su SKU efectivo: el suyo o «código-V{id}». Si ese SKU
     * cambia, el canal sigue teniendo el viejo y el enlace se rompería: se
     * guarda un enlace propio de la variante con el SKU que tiene el canal.
     */
    private function conservarEnlacesDeCanal(ItemVariant $variant, string $nuevo): void
    {
        $item = Item::find($variant->item_id);
        if (!$item) {
            return;
        }

        $viejo = $variant->sku ?: (($item->internal_id ?: $item->item_code) . '-V' . $variant->id);
        if ($viejo === $nuevo) {
            return;
        }

        $padres = MarketplaceProduct::where('item_id', $item->id)
            ->whereNull('item_variant_id')
            ->whereNotNull('external_sku')
            ->get();

        foreach ($padres as $p) {
            MarketplaceProduct::firstOrCreate(
                ['channel_id' => $p->channel_id, 'item_id' => $item->id, 'item_variant_id' => $variant->id],
                ['external_sku' => $viejo, 'sync_status' => $p->sync_status]
            );
        }
    }
}
