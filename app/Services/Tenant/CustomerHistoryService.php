<?php

namespace App\Services\Tenant;

use App\Models\Tenant\AuditLog;
use App\Models\Tenant\Document;
use App\Models\Tenant\Person;
use App\Models\Tenant\Purchase;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Historial completo de un cliente (Vista 360° y su reporte PDF).
 *
 * Reglas de cuenta, comunes a la ventana y al PDF:
 *  - Anulado (11), por anular (13) y rechazado por SUNAT (09) no son venta ni
 *    deuda.
 *  - Al convertir una nota de venta en boleta/factura el sistema COPIA la
 *    venta y sus pagos al comprobante. Una nota con comprobante válido queda
 *    «convertida»: se muestra con su vínculo pero no suma, para no contar la
 *    misma venta (ni la misma deuda) dos veces.
 *  - Las notas de crédito/débito (07/08) se listan pero no suman.
 *  - Pagado = suma de los pagos registrados; saldo = total − pagado. Es la
 *    misma cuenta que el listado de notas de venta.
 */
class CustomerHistoryService
{
    public const VOID = ['09', '11', '13'];

    /** Periodo [desde, hasta] por fecha de emisión; null = todo el historial. */
    private ?array $range = null;

    /**
     * @param int|null    $limit filas por sección (null = todas). Los totales
     *                           del resumen cubren TODO el periodo.
     * @param string|null $from  Y-m-d, inclusive
     * @param string|null $to    Y-m-d, inclusive
     */
    public function build(Person $person, ?int $limit = 30, ?string $from = null, ?string $to = null): array
    {
        $this->range = ($from || $to) ? [$from ?: '1900-01-01', $to ?: '2999-12-31'] : null;

        $documents  = $this->documents($person, $limit);
        $saleNotes  = $this->saleNotes($person, $limit);
        $quotations = $this->quotations($person, $limit);
        $purchases  = $this->purchases($person, $limit);

        $receivable = $this->receivable($person);
        $payable    = $this->payable($person);

        $lastPurchase = collect([
            $this->inRange($person->documents_where_customer(), 'date_of_issue')->whereNotIn('state_type_id', self::VOID)->max('date_of_issue'),
            $this->inRange($person->sale_notes_where_customer(), 'date_of_issue')->whereNotIn('state_type_id', self::VOID)->max('date_of_issue'),
        ])->filter()->max();

        return [
            'person' => [
                'id'         => $person->id,
                'type'       => $person->type,
                'name'       => $person->name,
                'trade_name' => $person->trade_name,
                'number'     => $person->number,
                'email'      => $person->email,
                'telephone'  => $person->telephone,
                'address'    => $person->address,
                'created_at' => optional($person->created_at)->format('Y-m-d H:i'),
                'updated_at' => optional($person->updated_at)->format('Y-m-d H:i'),
            ],
            'summary' => [
                'total_lifetime'   => $receivable['total'],
                'total_paid'       => $receivable['paid'],
                'total_pending'    => $receivable['pending'],
                'pending_count'    => $receivable['pending_count'],
                'annulled_count'   => $receivable['annulled_count'],
                'annulled_total'   => $receivable['annulled_total'],
                // Cobrado en ventas anuladas: el dinero entró y la venta no
                // existe. Queda a favor del cliente (o por devolver) y se
                // descuenta del saldo neto para que el resumen cuadre.
                'annulled_paid'    => $receivable['annulled_paid'],
                'net_pending'      => round($receivable['pending'] - $receivable['annulled_paid'], 2),
                'payable_total'    => $payable['total'],
                'payable_paid'     => $payable['paid'],
                'payable_pending'  => $payable['pending'],
                'payable_count'    => $payable['pending_count'],
                'doc_count'        => $this->inRange($person->documents_where_customer(), 'date_of_issue')->count(),
                'sn_count'         => $this->inRange($person->sale_notes_where_customer(), 'date_of_issue')->count(),
                'quotations_count' => $this->inRange(Quotation::where('customer_id', $person->id), 'date_of_issue')->count(),
                'purchases_count'  => $this->inRange(Purchase::where('supplier_id', $person->id), 'date_of_issue')->count(),
                'last_purchase'    => $lastPurchase ? substr($lastPurchase, 0, 10) : null,
            ],
            'period'       => $this->range ? ['from' => $from, 'to' => $to] : null,
            'top_products' => $this->topProducts($person, $limit ? 15 : 50),
            'documents'  => $documents,
            'sale_notes' => $saleNotes,
            'quotations' => $quotations,
            'purchases'  => $purchases,
            'activity'   => $this->activity($person),
        ];
    }

    // ── Secciones ───────────────────────────────────────────────────

    private function documents(Person $person, ?int $limit): Collection
    {
        // `documents` no tiene date_of_due: el vencimiento vive en `invoices`.
        $rows = $person->documents_where_customer()
            ->leftJoin('invoices', 'invoices.document_id', '=', 'documents.id')
            ->select('documents.id', 'documents.series', 'documents.number', 'documents.date_of_issue',
                'invoices.date_of_due', 'documents.total', 'documents.state_type_id',
                'documents.document_type_id', 'documents.currency_type_id',
                'documents.sale_note_id', 'documents.quotation_id', 'documents.external_id')
            ->tap(fn ($q) => $this->inRange($q, 'documents.date_of_issue'))
            ->with(['items', 'payments'])
            ->latest('documents.date_of_issue')->latest('documents.id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $saleNoteLabels  = $this->labels(SaleNote::class, $rows->pluck('sale_note_id'));
        $quotationLabels = $this->quotationLabels($rows->pluck('quotation_id'));

        return $rows->map(function ($d) use ($saleNoteLabels, $quotationLabels) {
            // Las notas de crédito/débito se listan pero no son venta ni deuda.
            [$paid, $pending] = $this->balance($d, !in_array($d->document_type_id, ['01', '03']));

            return [
                'id'          => $d->id,
                'label'       => "{$d->series}-{$d->number}",
                'pdf'         => "/print/document/{$d->external_id}/a4",
                'type'        => $this->documentTypeName($d->document_type_id),
                'date'        => $this->day($d->date_of_issue),
                'date_of_due' => $this->day($d->date_of_due),
                'total'       => (float) $d->total,
                'paid'        => $paid,
                'pending'     => $pending,
                'state'       => $d->state_type_id,
                'state_label' => $this->stateLabel($d->state_type_id),
                'currency'    => $d->currency_type_id,
                'from_sale_note' => $saleNoteLabels[$d->sale_note_id] ?? null,
                'from_quotation' => $quotationLabels[$d->quotation_id] ?? null,
                'items'       => $this->items($d->items),
                'payments'    => $this->payments($d->payments),
            ];
        })->values();
    }

    private function saleNotes(Person $person, ?int $limit): Collection
    {
        $rows = $person->sale_notes_where_customer()
            ->select('id', 'series', 'number', 'date_of_issue', 'total', 'state_type_id',
                'currency_type_id', 'quotation_id', 'document_id', 'external_id')
            ->tap(fn ($q) => $this->inRange($q, 'date_of_issue'))
            ->with(['items', 'payments'])
            ->latest('date_of_issue')->latest('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $quotationLabels = $this->quotationLabels($rows->pluck('quotation_id'));

        // Comprobantes emitidos desde cada nota (por sale_note_id o, en datos
        // antiguos, por sale_notes.document_id).
        $docs = Document::query()
            ->where(fn ($q) => $q->whereIn('sale_note_id', $rows->pluck('id'))
                ->orWhereIn('id', $rows->pluck('document_id')->filter()))
            ->get(['id', 'series', 'number', 'sale_note_id', 'state_type_id']);

        return $rows->map(function ($sn) use ($quotationLabels, $docs) {
            $linked = $docs->filter(fn ($d) => $d->sale_note_id == $sn->id || $d->id == $sn->document_id);
            $converted = $linked->contains(fn ($d) => !in_array($d->state_type_id, self::VOID));
            [$paid, $pending] = $this->balance($sn, $converted);

            return [
                'id'          => $sn->id,
                'label'       => "{$sn->series}-{$sn->number}",
                'pdf'         => "/sale-notes/print/{$sn->external_id}/a4",
                'date'        => $this->day($sn->date_of_issue),
                'total'       => (float) $sn->total,
                'paid'        => $paid,
                'pending'     => $pending,
                'state'       => $sn->state_type_id,
                'state_label' => $this->stateLabel($sn->state_type_id),
                'currency'    => $sn->currency_type_id,
                'converted'   => $converted,
                'from_quotation' => $quotationLabels[$sn->quotation_id] ?? null,
                'to_documents'   => $linked->map(fn ($d) => [
                    'label'  => "{$d->series}-{$d->number}",
                    'state'  => $d->state_type_id,
                    'valid'  => !in_array($d->state_type_id, self::VOID),
                ])->values(),
                'items'       => $this->items($sn->items),
                'payments'    => $this->payments($sn->payments),
            ];
        })->values();
    }

    private function quotations(Person $person, ?int $limit): Collection
    {
        $rows = Quotation::where('customer_id', $person->id)
            ->select('id', 'prefix', 'date_of_issue', 'date_of_due', 'total', 'state_type_id',
                'currency_type_id', 'changed', 'external_id')
            ->tap(fn ($q) => $this->inRange($q, 'date_of_issue'))
            ->with('items')
            ->latest('date_of_issue')->latest('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get();

        $ids = $rows->pluck('id');
        $notes = SaleNote::whereIn('quotation_id', $ids)->get(['id', 'series', 'number', 'quotation_id', 'state_type_id']);
        $docs  = Document::whereIn('quotation_id', $ids)->get(['id', 'series', 'number', 'quotation_id', 'state_type_id']);

        return $rows->map(function ($q) use ($notes, $docs) {
            $sales = $notes->where('quotation_id', $q->id)->concat($docs->where('quotation_id', $q->id))
                ->map(fn ($x) => [
                    'label' => "{$x->series}-{$x->number}",
                    'state' => $x->state_type_id,
                    'valid' => !in_array($x->state_type_id, self::VOID),
                ])->values();

            return [
                'id'          => $q->id,
                'label'       => "{$q->prefix}-{$q->id}",
                'pdf'         => "/quotations/print/{$q->external_id}/a4",
                'date'        => $this->day($q->date_of_issue),
                'date_of_due' => $this->day($q->date_of_due),
                'total'       => (float) $q->total,
                'state'       => $q->state_type_id,
                'state_label' => $this->stateLabel($q->state_type_id),
                'currency'    => $q->currency_type_id,
                'converted'   => $sales->isNotEmpty() || (bool) $q->changed,
                'to_sales'    => $sales,
                'items'       => $this->items($q->items),
            ];
        })->values();
    }

    /** Compras al mismo contacto cuando además es proveedor (cuentas por pagar). */
    private function purchases(Person $person, ?int $limit): Collection
    {
        return Purchase::where('supplier_id', $person->id)
            ->select('id', 'series', 'number', 'date_of_issue', 'date_of_due', 'total',
                'state_type_id', 'currency_type_id', 'document_type_id', 'external_id')
            ->tap(fn ($q) => $this->inRange($q, 'date_of_issue'))
            ->with(['items', 'payments'])
            ->latest('date_of_issue')->latest('id')
            ->when($limit, fn ($q) => $q->limit($limit))
            ->get()
            ->map(function ($p) {
                [$paid, $pending] = $this->balance($p, false);

                return [
                    'id'          => $p->id,
                    'label'       => "{$p->series}-{$p->number}",
                    'pdf'         => "/purchases/print/{$p->external_id}/a4",
                    'date'        => $this->day($p->date_of_issue),
                    'date_of_due' => $this->day($p->date_of_due),
                    'total'       => (float) $p->total,
                    'paid'        => $paid,
                    'pending'     => $pending,
                    'state'       => $p->state_type_id,
                    'state_label' => $this->stateLabel($p->state_type_id),
                    'currency'    => $p->currency_type_id,
                    'items'       => $this->items($p->items),
                    'payments'    => $this->payments($p->payments),
                ];
            })->values();
    }

    /**
     * Registro y cambios de datos. El alta sale de created_at (existe para
     * todos); quién la hizo y los cambios posteriores solo constan desde que
     * PersonAuditObserver escribe en audit_logs.
     */
    private function activity(Person $person): Collection
    {
        $activity = collect();
        try {
            $users = User::pluck('name', 'id');
            $activity = AuditLog::where('auditable_type', Person::class)
                ->where('auditable_id', $person->id)
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn ($a) => [
                    'date'    => optional($a->created_at)->format('Y-m-d H:i'),
                    'action'  => $a->action,
                    'user'    => $users[$a->user_id] ?? null,
                    'changes' => $a->action === 'update'
                        ? collect($a->new_values ?? [])->map(fn ($new, $field) => [
                            'field' => $field,
                            'old'   => data_get($a->old_values, $field),
                            'new'   => $new,
                        ])->values()
                        : [],
                ]);
        } catch (\Throwable $e) {
            // Sin bitácora en este tenant: queda solo la fecha de alta.
        }

        if (!$activity->contains('action', 'create')) {
            $activity->push([
                'date'    => optional($person->created_at)->format('Y-m-d H:i'),
                'action'  => 'create',
                'user'    => null,
                'changes' => [],
            ]);
        }

        return $activity->values();
    }

    // ── Totales sobre TODO el historial (no solo las filas listadas) ──

    /** Cuentas por cobrar: facturas/boletas + notas no convertidas. */
    private function receivable(Person $person): array
    {
        $void = "'" . implode("','", self::VOID) . "'";
        $id = (int) $person->id;

        $docs = DB::connection('tenant')->select("
            SELECT d.total,
                   (SELECT COALESCE(SUM(p.payment),0) FROM document_payments p WHERE p.document_id = d.id) AS paid,
                   d.state_type_id IN ($void) AS void
            FROM documents d WHERE d.customer_id = ? AND d.document_type_id IN ('01','03')" . $this->rangeSql('d'), $this->rangeBindings([$id]));

        $notes = DB::connection('tenant')->select("
            SELECT s.total,
                   (SELECT COALESCE(SUM(p.payment),0) FROM sale_note_payments p WHERE p.sale_note_id = s.id) AS paid,
                   s.state_type_id IN ($void) AS void,
                   EXISTS (SELECT 1 FROM documents d
                           WHERE (d.sale_note_id = s.id OR d.id = s.document_id)
                             AND d.state_type_id NOT IN ($void)) AS converted
            FROM sale_notes s WHERE s.customer_id = ?" . $this->rangeSql('s'), $this->rangeBindings([$id]));

        $rows = collect($docs)->concat(collect($notes)->reject(fn ($n) => $n->converted));

        return $this->totals($rows);
    }

    /** Cuentas por pagar: compras a este contacto como proveedor. */
    private function payable(Person $person): array
    {
        $void = "'" . implode("','", self::VOID) . "'";

        $rows = DB::connection('tenant')->select("
            SELECT c.total,
                   (SELECT COALESCE(SUM(p.payment),0) FROM purchase_payments p WHERE p.purchase_id = c.id) AS paid,
                   c.state_type_id IN ($void) AS void
            FROM purchases c WHERE c.supplier_id = ?" . $this->rangeSql('c'), $this->rangeBindings([(int) $person->id]));

        return $this->totals(collect($rows));
    }

    private function totals(Collection $rows): array
    {
        $valid = $rows->reject(fn ($r) => $r->void);
        $void  = $rows->filter(fn ($r) => $r->void);
        $pendings = $valid->map(fn ($r) => max(0, round((float) $r->total - (float) $r->paid, 2)));

        return [
            'total'          => round($valid->sum(fn ($r) => (float) $r->total), 2),
            'paid'           => round($valid->sum(fn ($r) => (float) $r->paid), 2),
            'pending'        => round($pendings->sum(), 2),
            'pending_count'  => $pendings->filter(fn ($p) => $p > 0)->count(),
            'annulled_count' => $void->count(),
            'annulled_total' => round($void->sum(fn ($r) => (float) $r->total), 2),
            'annulled_paid'  => round($void->sum(fn ($r) => (float) $r->paid), 2),
        ];
    }

    /**
     * Productos que más compra el cliente en el periodo: solo ventas vigentes
     * (facturas/boletas válidas y notas no anuladas ni convertidas, para no
     * contar dos veces lo que pasó de nota a comprobante).
     */
    private function topProducts(Person $person, int $limit): Collection
    {
        $void = "'" . implode("','", self::VOID) . "'";
        $id = (int) $person->id;

        $code = fn ($t) => "JSON_UNQUOTE(JSON_EXTRACT($t.item, '$.internal_id'))";
        $desc = fn ($t) => "JSON_UNQUOTE(JSON_EXTRACT($t.item, '$.description'))";

        $sql = "
            SELECT x.item_id, MAX(x.code) AS code, MAX(x.description) AS description,
                   SUM(x.quantity) AS quantity, SUM(x.total) AS total,
                   COUNT(DISTINCT x.sale) AS sales, MAX(x.date_of_issue) AS last_date
            FROM (
                SELECT i.item_id, {$code('i')} AS code, {$desc('i')} AS description,
                       i.quantity, i.total, CONCAT('d', d.id) AS sale, d.date_of_issue
                FROM document_items i JOIN documents d ON d.id = i.document_id
                WHERE d.customer_id = ? AND d.document_type_id IN ('01','03')
                  AND d.state_type_id NOT IN ($void)" . $this->rangeSql('d') . "
                UNION ALL
                SELECT i.item_id, {$code('i')}, {$desc('i')},
                       i.quantity, i.total, CONCAT('s', s.id), s.date_of_issue
                FROM sale_note_items i JOIN sale_notes s ON s.id = i.sale_note_id
                WHERE s.customer_id = ? AND s.state_type_id NOT IN ($void)
                  AND NOT EXISTS (SELECT 1 FROM documents d2
                                  WHERE (d2.sale_note_id = s.id OR d2.id = s.document_id)
                                    AND d2.state_type_id NOT IN ($void))" . $this->rangeSql('s') . "
            ) x
            GROUP BY x.item_id
            ORDER BY total DESC
            LIMIT " . (int) $limit;

        $bindings = array_merge($this->rangeBindings([$id]), $this->rangeBindings([$id]));

        return collect(DB::connection('tenant')->select($sql, $bindings))->map(fn ($r) => [
            'code'        => $r->code,
            'description' => $r->description,
            'quantity'    => (float) $r->quantity,
            'total'       => round((float) $r->total, 2),
            'sales'       => (int) $r->sales,
            'last_date'   => $this->day($r->last_date),
        ]);
    }

    // ── Ayudantes ───────────────────────────────────────────────────

    /** Aplica el periodo (si hay) a una consulta Eloquent. */
    private function inRange($query, string $column)
    {
        return $this->range ? $query->whereBetween($column, $this->range) : $query;
    }

    private function rangeSql(string $alias): string
    {
        return $this->range ? " AND $alias.date_of_issue BETWEEN ? AND ?" : '';
    }

    private function rangeBindings(array $bindings): array
    {
        return $this->range ? array_merge($bindings, $this->range) : $bindings;
    }

    /** [pagado, saldo]. Anulada o convertida no deja saldo propio. */
    private function balance($doc, bool $converted): array
    {
        $paid = round((float) $doc->payments->sum('payment'), 2);
        $pending = ($converted || in_array($doc->state_type_id, self::VOID))
            ? 0
            : max(0, round((float) $doc->total - $paid, 2));

        return [$paid, $pending];
    }

    /** Lo comprado: descripción congelada en el JSON `item` de la línea. */
    private function items($items): Collection
    {
        return $items->map(fn ($i) => [
            'code'        => data_get($i->item, 'internal_id'),
            'description' =>data_get($i->item, 'description') ?: $i->name_product_pdf,
            'quantity'    => (float) $i->quantity,
            'unit_price'  => (float) $i->unit_price,
            'total'       => (float) $i->total,
        ])->values();
    }

    private function payments($payments): Collection
    {
        return $payments->map(fn ($p) => [
            'date'      => $this->day($p->date_of_payment),
            'method'    => optional($p->payment_method_type)->description ?? $p->payment_method_type_id,
            'reference' => $p->reference,
            'amount'    => (float) $p->payment,
        ])->values();
    }

    private function labels(string $model, Collection $ids): array
    {
        $ids = $ids->filter()->unique();
        if ($ids->isEmpty()) {
            return [];
        }

        return $model::whereIn('id', $ids)->get(['id', 'series', 'number'])
            ->mapWithKeys(fn ($x) => [$x->id => "{$x->series}-{$x->number}"])->all();
    }

    private function quotationLabels(Collection $ids): array
    {
        $ids = $ids->filter()->unique();
        if ($ids->isEmpty()) {
            return [];
        }

        return Quotation::whereIn('id', $ids)->get(['id', 'prefix'])
            ->mapWithKeys(fn ($q) => [$q->id => "{$q->prefix}-{$q->id}"])->all();
    }

    /** Las fechas llegan como Carbon o como texto según el cast del modelo. */
    private function day($value): ?string
    {
        return $value ? substr((string) $value, 0, 10) : null;
    }

    private function documentTypeName(?string $type): string
    {
        return ['01' => 'Factura', '03' => 'Boleta', '07' => 'Nota de crédito', '08' => 'Nota de débito'][$type] ?? (string) $type;
    }

    public function stateLabel(?string $state): string
    {
        return [
            '01' => 'Registrado', '03' => 'Enviado', '05' => 'Aceptado', '07' => 'Observado',
            '09' => 'Rechazado', '11' => 'Anulado', '13' => 'Por anular',
        ][$state] ?? (string) $state;
    }
}
