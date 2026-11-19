<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Item;
use App\Models\Tenant\ItemVariant;
use App\Models\Tenant\MarketplaceChannel;
use App\Models\Tenant\MarketplaceProduct;
use App\Services\Marketplace\FalabellaService;
use Hyn\Tenancy\Environment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Enlazar con Saga: compara el catálogo REAL de Saga con los productos del
 * sistema, en los dos sentidos.
 *
 *  - En Saga y no enlazados aquí: su stock y sus ventas no llegan al sistema.
 *  - Enlazados aquí con un SKU que Saga ya no tiene (o a un producto borrado).
 *  - Productos del sistema sin enlace a Saga.
 *
 * Solo LEE de Saga. Enlazar o quitar un enlace solo cambia marketplace_products.
 */
class SagaLinkController extends Controller
{
    public function panel(Request $request)
    {
        $channel = MarketplaceChannel::platform('falabella')->first();
        if (!$channel) {
            return view('tenant.marketplace_orders.links', ['sinCanal' => true]);
        }

        $service = new FalabellaService($channel);

        try {
            $catalogo = $this->catalogo($channel, $service, $request->boolean('refrescar'));
        } catch (\Throwable $e) {
            return view('tenant.marketplace_orders.links', ['sinCanal' => false, 'error' => $e->getMessage()]);
        }

        $index = $service->skuIndex();
        $opciones = $this->opcionesErp();

        // 1. En Saga, sin enlazar.
        $soloSaga = [];
        foreach ($catalogo as $sku => $p) {
            if (isset($index[$sku])) continue;
            $soloSaga[] = $p + ['sugerencia' => $this->sugerir($p, $opciones)];
        }

        // 2. Enlazados con un SKU que Saga no tiene, o a un producto borrado.
        $rotos = [];
        foreach ($index as $sku => $t) {
            if ($t['excluded'] || isset($catalogo[$sku])) continue;
            $rotos[] = [
                'sku'        => $sku,
                'mapping_id' => $t['mapping_id'],
                'expanded'   => $t['expanded'],
                'item_id'    => $t['item']->id,
                'variant_id' => $t['variant']->id ?? null,
                'nombre'     => $this->nombre($t['item'], $t['variant']),
                'codigo'     => $t['variant']->sku ?? $t['item']->internal_id,
                'motivo'     => 'Saga no tiene este SKU',
            ];
        }
        $borrados = MarketplaceProduct::where('channel_id', $channel->id)
            ->whereDoesntHave('item')
            ->get();
        foreach ($borrados as $m) {
            $rotos[] = [
                'sku' => $m->external_sku, 'mapping_id' => $m->id, 'expanded' => false,
                'item_id' => null, 'variant_id' => null,
                'nombre' => 'Producto borrado (#' . $m->item_id . ')', 'codigo' => '',
                'motivo' => 'El producto ya no existe en el sistema',
            ];
        }

        // 3. Productos del sistema sin ningún enlace a Saga.
        $enlazados = [];
        foreach ($index as $t) {
            $enlazados[$t['variant'] ? 'v' . $t['variant']->id : 'i' . $t['item']->id] = true;
        }
        $soloSistema = array_values(array_filter($opciones, fn ($o) => !isset($enlazados[$o['key']])));

        return view('tenant.marketplace_orders.links', [
            'sinCanal'    => false,
            'soloSaga'    => $soloSaga,
            'rotos'       => $rotos,
            'soloSistema' => $soloSistema,
            'opciones'    => $opciones,
            'totalSaga'   => count($catalogo),
            'totalOk'     => count($catalogo) - count($soloSaga),
        ]);
    }

    /**
     * Enlaza un SKU de Saga con un producto (o una variante) del sistema.
     */
    public function link(Request $request)
    {
        $data = $request->validate([
            'sku'     => 'required|string|max:100',
            'destino' => 'required|string|max:300',
        ]);

        $channel = MarketplaceChannel::platform('falabella')->first();
        if (!$channel) {
            return response()->json(['error' => 'La tienda no tiene canal de Saga.'], 422);
        }

        if (!preg_match('/\[#(i|v)(\d+)\]\s*$/', $data['destino'], $m)) {
            return response()->json(['error' => 'Elige un producto de la lista.'], 422);
        }

        $sku = trim($data['sku']);
        $service = new FalabellaService($channel);

        if (!array_key_exists($sku, $this->catalogo($channel, $service))) {
            return response()->json(['error' => "Saga no tiene el SKU «{$sku}». Actualiza la lista."], 422);
        }

        $index = $service->skuIndex();
        if (isset($index[$sku]) && !$index[$sku]['excluded']) {
            return response()->json(['error' => "El SKU «{$sku}» ya está enlazado a «"
                . $this->nombre($index[$sku]['item'], $index[$sku]['variant']) . '».'], 422);
        }

        if ($m[1] === 'v') {
            $variant = ItemVariant::find((int) $m[2]);
            if (!$variant) {
                return response()->json(['error' => 'La variante ya no existe.'], 422);
            }
            $item = Item::find($variant->item_id);
        } else {
            $variant = null;
            $item = Item::find((int) $m[2]);
            if ($item && $item->has_variants && $item->variants()->count() > 0) {
                return response()->json(['error' => 'Este producto tiene variantes: elige la variante que corresponde a este SKU.'], 422);
            }
        }
        if (!$item) {
            return response()->json(['error' => 'El producto ya no existe.'], 422);
        }

        MarketplaceProduct::updateOrCreate(
            ['channel_id' => $channel->id, 'item_id' => $item->id, 'item_variant_id' => $variant->id ?? null],
            ['external_sku' => $sku, 'sync_status' => 'synced', 'last_error' => null]
        );

        return response()->json([
            'success' => true,
            'message' => "Enlazado: {$sku} → " . $this->nombre($item, $variant)
                . '. Su stock se iguala con Saga en la próxima sincronización (máx. 15 min).',
        ]);
    }

    /**
     * Quita un enlace. Si es una variante enlazada a través de su producto,
     * se excluye solo esa variante (el resto del producto sigue enlazado).
     */
    public function unlink(Request $request)
    {
        $data = $request->validate([
            'mapping_id' => 'required|integer',
            'variant_id' => 'nullable|integer',
            'sku'        => 'nullable|string|max:100',
        ]);

        $mapping = MarketplaceProduct::find($data['mapping_id']);
        if (!$mapping) {
            return response()->json(['success' => true, 'message' => 'El enlace ya no existía.']);
        }

        if (!$mapping->item_variant_id && !empty($data['variant_id'])) {
            MarketplaceProduct::updateOrCreate(
                ['channel_id' => $mapping->channel_id, 'item_id' => $mapping->item_id, 'item_variant_id' => $data['variant_id']],
                ['external_sku' => $data['sku'] ?: null, 'sync_status' => 'excluded']
            );

            return response()->json(['success' => true, 'message' => 'Variante quitada del enlace.']);
        }

        $mapping->delete();

        return response()->json(['success' => true, 'message' => 'Enlace quitado.']);
    }

    /** Catálogo de Saga, guardado 10 minutos: leerlo entero tarda varios segundos. */
    private function catalogo(MarketplaceChannel $channel, FalabellaService $service, bool $refrescar = false): array
    {
        $tenant = optional(app(Environment::class)->tenant())->uuid ?? 'x';
        $key = "saga_catalog_{$tenant}_{$channel->id}";

        if ($refrescar) {
            Cache::forget($key);
        }

        return Cache::remember($key, 600, fn () => $service->sagaCatalog());
    }

    /**
     * Productos y variantes del sistema para elegir, con la etiqueta que
     * lleva la clave al final: «Nombre · código [#i12]» o «[#v34]».
     */
    private function opcionesErp(): array
    {
        $items = Item::where('active', 1)
            ->where('unit_type_id', '!=', 'ZZ')
            ->with(['variants' => fn ($q) => $q->where('is_active', 1)])
            ->orderBy('description')
            ->get(['id', 'description', 'internal_id', 'item_code', 'has_variants']);

        $out = [];
        foreach ($items as $it) {
            if ($it->has_variants && $it->variants->count() > 0) {
                foreach ($it->variants as $v) {
                    $nombre = $this->nombre($it, $v);
                    $out[] = [
                        'key' => 'v' . $v->id, 'item_id' => $it->id, 'variant_id' => $v->id,
                        'nombre' => $nombre, 'codigo' => (string) $v->sku,
                        'codigos' => array_filter([$v->sku, $v->barcode]),
                        'label' => $nombre . ($v->sku ? ' · ' . $v->sku : '') . ' [#v' . $v->id . ']',
                    ];
                }
            } else {
                $out[] = [
                    'key' => 'i' . $it->id, 'item_id' => $it->id, 'variant_id' => null,
                    'nombre' => $it->description, 'codigo' => (string) $it->internal_id,
                    'codigos' => array_filter([$it->internal_id, $it->item_code]),
                    'label' => $it->description . ($it->internal_id ? ' · ' . $it->internal_id : '') . ' [#i' . $it->id . ']',
                ];
            }
        }

        return $out;
    }

    /**
     * Mejor candidato del sistema para un producto de Saga: primero por código
     * (ignorando mayúsculas, espacios y guiones), luego por parecido del nombre.
     */
    private function sugerir(array $saga, array $opciones): ?array
    {
        $sku = $this->normal($saga['sku'], false);
        foreach ($opciones as $o) {
            foreach ($o['codigos'] as $c) {
                if ($sku !== '' && $this->normal($c, false) === $sku) {
                    return ['label' => $o['label'], 'por' => 'código'];
                }
            }
        }

        $nombre = $this->normal($saga['name'], true);
        if ($nombre === '') {
            return null;
        }

        $mejor = null;
        $mejorPct = 0;
        foreach ($opciones as $o) {
            similar_text($nombre, $this->normal($o['nombre'], true), $pct);
            if ($pct > $mejorPct) {
                $mejorPct = $pct;
                $mejor = $o;
            }
        }

        return ($mejor && $mejorPct >= 60)
            ? ['label' => $mejor['label'], 'por' => 'nombre (' . round($mejorPct) . '% parecido)']
            : null;
    }

    private function normal(?string $s, bool $espacios): string
    {
        $s = Str::lower(Str::ascii((string) $s));
        $s = preg_replace($espacios ? '/[^a-z0-9]+/' : '/[^a-z0-9]/', $espacios ? ' ' : '', $s);

        return trim($s);
    }

    private function nombre(Item $item, ?ItemVariant $variant): string
    {
        return $variant
            ? $item->description . ' — ' . ($variant->display_name ?: 'Variante #' . $variant->id)
            : (string) $item->description;
    }
}
