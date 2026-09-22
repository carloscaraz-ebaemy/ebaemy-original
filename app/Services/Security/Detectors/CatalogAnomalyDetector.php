<?php

namespace App\Services\Security\Detectors;

use App\Services\Security\Alert;
use App\Services\Security\Contracts\DetectorModule;
use App\Services\Security\ScanContext;
use App\Services\Security\Severity;
use Illuminate\Support\Facades\DB;

/**
 * Modulo 2 — Errores de catalogo.
 *
 * Un precio en cero o un margen negativo no son un ataque, pero cuestan dinero
 * igual y pasan desapercibidos durante dias. Este modulo los saca a la luz.
 *
 * Las alertas van AGRUPADAS por tipo de problema, no una por producto: si un
 * import deja 300 articulos sin precio, se quiere un aviso, no 300.
 *
 * Ojo con el nombre del producto: en este esquema `items.name` esta vacio y el
 * nombre real vive en `items.description`.
 */
class CatalogAnomalyDetector implements DetectorModule
{
    private const SAMPLE = 20;

    private $db;

    public function key(): string   { return 'catalog_anomalies'; }
    public function label(): string { return 'Errores de catalogo'; }
    public function scope(): string { return 'tenant'; }

    public function detect(ScanContext $context): array
    {
        $this->db = $this->resolveConnection($context);

        return array_values(array_filter(array_merge(
            [$this->zeroPrice($context)],
            $this->marginProblems($context),
            $this->priceSwings($context),
            $this->stockProblems($context),
            [$this->crossChannelGap($context)],
        )));
    }

    // ── Precio en cero o invalido ────────────────────────────────────────────

    private function zeroPrice(ScanContext $context): ?Alert
    {
        $rows = $this->items($context)
            ->where(function ($q) {
                $q->whereNull('sale_unit_price')->orWhere('sale_unit_price', '<=', 0);
            })
            ->limit(500)
            ->get(['id', 'internal_id', 'description', 'sale_unit_price']);

        if ($rows->isEmpty()) return null;

        return new Alert(
            module: $this->key(),
            type: 'precio_cero',
            severity: Severity::CRITICA,
            title: sprintf('%d producto(s) activos con precio en cero o invalido', $rows->count()),
            recommendation: 'Estos productos se pueden comprar a S/ 0.00 ahora mismo. Despublicalos o corrige el precio antes de seguir con cualquier otra cosa. Si son muchos y aparecieron de golpe, revisa la ultima importacion: casi siempre es una columna mal mapeada.',
            evidence: ['total' => $rows->count(), 'productos' => $this->sample($rows)],
            tenant: $context->tenant,
            dedupeKey: "{$context->scopeKey()}:catalog:zero_price:" . $this->digest($rows),
        );
    }

    // ── Precio bajo el costo y margen insuficiente ───────────────────────────

    private function marginProblems(ScanContext $context): array
    {
        $minMargin = (float) $context->config->get('catalog_anomalies.min_margin_pct', 10);

        $rows = $this->items($context)
            ->where('sale_unit_price', '>', 0)
            ->where('purchase_unit_price', '>', 0)
            ->limit(2000)
            ->get(['id', 'internal_id', 'description', 'sale_unit_price', 'purchase_unit_price', 'min_margin_pct']);

        $belowCost = [];
        $thinMargin = [];

        foreach ($rows as $row) {
            $price = (float) $row->sale_unit_price;
            $cost  = (float) $row->purchase_unit_price;

            if ($price < $cost) {
                $belowCost[] = [
                    'producto' => $this->label_($row),
                    'precio'   => round($price, 2),
                    'costo'    => round($cost, 2),
                    'perdida'  => round($cost - $price, 2),
                ];
                continue;
            }

            // Margen SOBRE LA VENTA, que es como se mide en este sistema.
            $margin    = $price > 0 ? (($price - $cost) / $price) * 100 : 0;
            $threshold = $row->min_margin_pct !== null ? (float) $row->min_margin_pct : $minMargin;

            if ($margin < $threshold) {
                $thinMargin[] = [
                    'producto' => $this->label_($row),
                    'margen'   => round($margin, 2) . '%',
                    'minimo'   => round($threshold, 2) . '%',
                    'precio'   => round($price, 2),
                    'costo'    => round($cost, 2),
                ];
            }
        }

        $alerts = [];

        if ($belowCost) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'precio_bajo_costo',
                severity: Severity::ALTA,
                title: sprintf('%d producto(s) se venden por debajo de su costo', count($belowCost)),
                recommendation: 'Cada venta de estos productos pierde dinero. Corrige el precio o el costo: a veces el error esta en el costo, cargado con IGV cuando no correspondia. Revisa tambien el `floor_price` de esos articulos.',
                evidence: ['total' => count($belowCost), 'productos' => array_slice($belowCost, 0, self::SAMPLE)],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:catalog:below_cost:" . md5(json_encode(array_column($belowCost, 'producto'))),
            );
        }

        if ($thinMargin) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'margen_insuficiente',
                severity: Severity::MEDIA,
                title: sprintf('%d producto(s) por debajo del margen minimo', count($thinMargin)),
                recommendation: 'No pierdes dinero, pero vendes casi sin ganancia. Revisa si subio el costo de compra sin que se actualizara el precio de venta. Si el margen bajo es intencional (liquidacion), sube el `min_margin_pct` de esos articulos para que dejen de avisar.',
                evidence: ['total' => count($thinMargin), 'productos' => array_slice($thinMargin, 0, self::SAMPLE)],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:catalog:thin_margin:" . md5(json_encode(array_column($thinMargin, 'producto'))),
            );
        }

        return $alerts;
    }

    // ── Saltos de precio ─────────────────────────────────────────────────────

    private function priceSwings(ScanContext $context): array
    {
        $dropPct = (float) $context->config->get('catalog_anomalies.price_drop_pct', 30);
        $risePct = (float) $context->config->get('catalog_anomalies.price_rise_pct', 50);
        $since   = $context->now->subHours((int) $context->config->get('catalog_anomalies.price_lookback_hours', 24));

        try {
            $rows = $this->db->table('item_price_history as h')
                ->leftJoin('items as i', 'i.id', '=', 'h.item_id')
                ->where('h.created_at', '>=', $since)
                ->where('h.old_price', '>', 0)
                ->limit(2000)
                ->get(['h.item_id', 'h.old_price', 'h.new_price', 'h.changed_by', 'h.source', 'h.created_at', 'i.internal_id', 'i.description']);
        } catch (\Throwable $e) {
            // `item_price_history` puede no existir en un tenant antiguo.
            return [];
        }

        $drops = [];
        $rises = [];

        foreach ($rows as $row) {
            $old = (float) $row->old_price;
            $new = (float) $row->new_price;
            if ($old <= 0) continue;

            $change = (($new - $old) / $old) * 100;

            $entry = [
                'producto' => $this->label_($row),
                'antes'    => round($old, 2),
                'ahora'    => round($new, 2),
                'variacion'=> round($change, 1) . '%',
                'quien'    => $row->changed_by,
                'origen'   => $row->source,
                'cuando'   => (string) $row->created_at,
            ];

            if ($change <= -$dropPct) $drops[] = $entry;
            if ($change >= $risePct)  $rises[] = $entry;
        }

        $alerts = [];

        if ($drops) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'caida_de_precio',
                severity: Severity::ALTA,
                title: sprintf('%d producto(s) bajaron de precio mas de %s%%', count($drops), $dropPct),
                recommendation: 'Si la rebaja no es una promocion planificada, es un error de digitacion o de importacion y te esta costando dinero en cada venta. La evidencia dice quien la hizo y desde que origen: confirma con esa persona antes de revertir.',
                evidence: ['total' => count($drops), 'cambios' => array_slice($drops, 0, self::SAMPLE)],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:catalog:price_drop:" . md5(json_encode(array_column($drops, 'producto'))),
            );
        }

        if ($rises) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'subida_de_precio',
                severity: Severity::MEDIA,
                title: sprintf('%d producto(s) subieron de precio mas de %s%%', count($rises), $risePct),
                recommendation: 'Una subida brusca suele ser un decimal mal puesto (S/ 45.00 que quedo en S/ 4500.00). No pierdes dinero, pero espantas la venta y el producto desaparece de las comparaciones de precio de los marketplaces.',
                evidence: ['total' => count($rises), 'cambios' => array_slice($rises, 0, self::SAMPLE)],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:catalog:price_rise:" . md5(json_encode(array_column($rises, 'producto'))),
            );
        }

        return $alerts;
    }

    // ── Stock ────────────────────────────────────────────────────────────────

    private function stockProblems(ScanContext $context): array
    {
        $critical = (float) $context->config->get('catalog_anomalies.critical_stock', 3);
        $alerts   = [];

        $negative = $this->db->table('item_warehouse as iw')
            ->leftJoin('items as i', 'i.id', '=', 'iw.item_id')
            ->where('iw.stock', '<', 0)
            ->limit(500)
            ->get(['iw.item_id', 'iw.warehouse_id', 'iw.stock', 'i.internal_id', 'i.description']);

        if ($negative->isNotEmpty()) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'stock_negativo',
                severity: Severity::ALTA,
                title: sprintf('%d registro(s) de stock en negativo', $negative->count()),
                recommendation: 'Un stock negativo significa que se vendio mas de lo que habia registrado: o falto un ingreso, o una salida se conto dos veces. Corre `php artisan stock:reconcile` para ver la divergencia antes de ajustar nada a mano.',
                evidence: [
                    'total'     => $negative->count(),
                    'registros' => $negative->take(self::SAMPLE)->map(fn ($r) => [
                        'producto' => $this->label_($r),
                        'almacen'  => $r->warehouse_id,
                        'stock'    => (float) $r->stock,
                    ])->values()->all(),
                ],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:catalog:negative_stock:" . $this->digest($negative),
            );
        }

        $published = $this->items($context)
            ->where('stock', '<=', 0)
            ->limit(500)
            ->get(['id', 'internal_id', 'description', 'stock']);

        if ($published->isNotEmpty()) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'publicado_sin_stock',
                severity: Severity::MEDIA,
                title: sprintf('%d producto(s) publicados con stock en cero', $published->count()),
                recommendation: 'El cliente los ve, los agrega al carrito y el pedido se cae al final o llega y no lo puedes despachar. Despublicalos o repone stock. Si son de reposicion continua, deja anotado que es intencional.',
                evidence: ['total' => $published->count(), 'productos' => $this->sample($published)],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:catalog:published_no_stock:" . $this->digest($published),
            );
        }

        $low = $this->items($context)
            ->where('stock', '>', 0)
            ->where('stock', '<=', $critical)
            ->limit(500)
            ->get(['id', 'internal_id', 'description', 'stock']);

        if ($low->isNotEmpty()) {
            $alerts[] = new Alert(
                module: $this->key(),
                type: 'stock_critico',
                severity: Severity::BAJA,
                title: sprintf('%d producto(s) con stock critico (<= %s)', $low->count(), $critical),
                recommendation: 'Programa la reposicion. No es urgente hoy, pero si se agota con publicaciones activas en los marketplaces, se convierte en cancelaciones y eso si pega en la reputacion de la cuenta.',
                evidence: ['total' => $low->count(), 'productos' => $this->sample($low)],
                tenant: $context->tenant,
                dedupeKey: "{$context->scopeKey()}:catalog:low_stock:" . $this->digest($low),
            );
        }

        return $alerts;
    }

    // ── Diferencia de precio entre canales ───────────────────────────────────

    private function crossChannelGap(ScanContext $context): ?Alert
    {
        $maxGap = (float) $context->config->get('catalog_anomalies.cross_channel_pct', 25);

        $rows = $this->items($context)
            ->where('sale_unit_price', '>', 0)
            ->where('mp_price', '>', 0)
            ->limit(2000)
            ->get(['id', 'internal_id', 'description', 'sale_unit_price', 'mp_price']);

        $gaps = [];

        foreach ($rows as $row) {
            $store  = (float) $row->sale_unit_price;
            $market = (float) $row->mp_price;
            $gap    = abs($store - $market) / max($store, $market) * 100;

            if ($gap <= $maxGap) continue;

            $gaps[] = [
                'producto'    => $this->label_($row),
                'tienda'      => round($store, 2),
                'marketplace' => round($market, 2),
                'diferencia'  => round($gap, 1) . '%',
            ];
        }

        if (!$gaps) return null;

        return new Alert(
            module: $this->key(),
            type: 'precio_dispar_entre_canales',
            severity: Severity::MEDIA,
            title: sprintf('%d producto(s) con mas de %s%% de diferencia entre tienda y marketplace', count($gaps), $maxGap),
            recommendation: 'El mismo articulo cuesta muy distinto segun donde lo mire el cliente. Decide cual es el precio correcto y sincroniza: si el barato es el del marketplace, estas regalando margen; si es el de la tienda, el cliente que compara se va.',
            evidence: ['total' => count($gaps), 'productos' => array_slice($gaps, 0, self::SAMPLE)],
            tenant: $context->tenant,
            dedupeKey: "{$context->scopeKey()}:catalog:channel_gap:" . md5(json_encode(array_column($gaps, 'producto'))),
        );
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    /** Base comun: solo productos activos y no excluidos por configuracion. */
    private function items(ScanContext $context)
    {
        $query = $this->db->table('items')->where('active', 1);

        $ignore = (array) $context->config->get('catalog_anomalies.ignore_item_ids', []);
        if ($ignore) {
            $query->whereNotIn('id', $ignore);
        }

        return $query;
    }

    /** En este esquema el nombre del producto vive en `description`. */
    private function label_($row): string
    {
        $name = trim((string) ($row->description ?? ''));
        $code = trim((string) ($row->internal_id ?? ''));

        if ($name === '') $name = 'Producto #' . ($row->item_id ?? $row->id ?? '?');

        return $code !== '' ? "{$code} — {$name}" : $name;
    }

    private function sample($rows): array
    {
        return $rows->take(self::SAMPLE)->map(fn ($r) => [
            'producto' => $this->label_($r),
            'precio'   => isset($r->sale_unit_price) ? round((float) $r->sale_unit_price, 2) : null,
            'stock'    => isset($r->stock) ? (float) $r->stock : null,
        ])->values()->all();
    }

    /** Firma del conjunto: si cambia la lista, vuelve a avisar. */
    private function digest($rows): string
    {
        return md5($rows->pluck('id')->implode(',') . $rows->pluck('item_id')->implode(','));
    }

    private function resolveConnection(ScanContext $context)
    {
        if ($context->tenant === null) {
            return DB::connection(config('database.default'));
        }

        $override = $context->config->get('read_connection');

        return DB::connection($override ?: 'tenant');
    }
}
