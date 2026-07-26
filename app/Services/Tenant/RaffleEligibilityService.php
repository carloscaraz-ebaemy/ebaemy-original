<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Document;
use App\Models\Tenant\Order;
use App\Models\Tenant\Person;
use App\Models\Tenant\Raffle;
use App\Models\Tenant\RaffleParticipant;
use App\Models\Tenant\SaleNote;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve qué clientes son elegibles para un sorteo, a partir de sus pedidos.
 *
 * Fuentes soportadas (configurables por sorteo en `sources`):
 *   documents   → facturas / boletas   (state_type_id válido)
 *   sale_notes  → notas de venta
 *   orders      → pedidos de tienda virtual
 *
 * Criterios de elegibilidad (todos opcionales salvo el pago):
 *   require_paid      → solo pedidos con pago confirmado
 *   purchase_from/to  → rango de fecha de compra
 *   min_amount        → monto MÍNIMO ACUMULADO por cliente en el periodo
 *   establishment_id  → sucursal (aplica a documents / sale_notes)
 *   channel_id        → canal de venta (aplica a orders)
 *   category_ids      → categorías de producto (documents / sale_notes)
 *   item_ids          → productos concretos (documents / sale_notes)
 *
 * NOTA sobre filtros de producto: los pedidos de tienda virtual guardan sus
 * ítems en una columna JSON (no hay tabla `order_items`), así que cuando el
 * sorteo filtra por categoría o producto la fuente `orders` queda fuera del
 * universo. El panel lo advierte explícitamente. En la práctica esos pedidos
 * suelen tener su nota de venta / comprobante asociado, que sí entra.
 *
 * Deduplicación: un cliente aparece UNA sola vez aunque tenga varios pedidos.
 * La clave es documento → email → teléfono → person_id (misma que usa
 * RaffleParticipant::dedupeKeyFor), y se acumulan orders_count / total_amount.
 */
class RaffleEligibilityService
{
    /** Estados de comprobante que cuentan como venta válida (no anulada/rechazada). */
    private const VALID_STATE_TYPES = ['01', '03', '05', '07'];

    /**
     * Universo de clientes elegibles, ya deduplicado.
     *
     * @return \Illuminate\Support\Collection<int, array> filas con
     *         [person_id, full_name, document, email, phone, dedupe_key,
     *          orders_count, total_amount, last_purchase_at]
     */
    public function eligible(Raffle $raffle)
    {
        $rows = collect();

        foreach ($this->sourcesFor($raffle) as $source) {
            $rows = $rows->concat($this->rowsFor($raffle, $source));
        }

        $merged = [];

        foreach ($rows as $row) {
            $key = $row['dedupe_key'];

            if (!isset($merged[$key])) {
                $merged[$key] = $row;
                continue;
            }

            // Mismo cliente con otro pedido: acumular sin duplicarlo.
            $merged[$key]['orders_count'] += $row['orders_count'];
            $merged[$key]['total_amount'] += $row['total_amount'];

            if ($row['last_purchase_at'] && (!$merged[$key]['last_purchase_at']
                || $row['last_purchase_at'] > $merged[$key]['last_purchase_at'])) {
                $merged[$key]['last_purchase_at'] = $row['last_purchase_at'];
            }

            // Completar datos de contacto faltantes con los de otra fuente.
            foreach (['email', 'phone', 'document', 'person_id'] as $field) {
                if (empty($merged[$key][$field]) && !empty($row[$field])) {
                    $merged[$key][$field] = $row[$field];
                }
            }
        }

        $result = collect(array_values($merged));

        // El monto mínimo se evalúa sobre el ACUMULADO del cliente, no por pedido:
        // así "compras de S/ 200 o más" incluye a quien compró 2 veces S/ 100.
        if ($raffle->min_amount > 0) {
            $result = $result->filter(fn ($r) => $r['total_amount'] >= (float) $raffle->min_amount);
        }

        return $result->sortByDesc('total_amount')->values();
    }

    /** Solo el conteo (para el indicador "clientes elegibles"). */
    public function countEligible(Raffle $raffle): int
    {
        return $this->eligible($raffle)->count();
    }

    /**
     * Sincroniza la lista de participantes del sorteo desde los pedidos elegibles.
     * Es idempotente: los que ya existen NO se tocan (conservan su token y su
     * aceptación); solo se insertan los nuevos.
     *
     * @return array{created:int, existing:int, total:int}
     */
    public function syncParticipants(Raffle $raffle): array
    {
        $eligible = $this->eligible($raffle);

        $existing = $raffle->participants()->pluck('dedupe_key')->flip();

        $created = 0;

        foreach ($eligible as $row) {
            if ($existing->has($row['dedupe_key'])) {
                continue;
            }

            RaffleParticipant::create([
                'raffle_id'        => $raffle->id,
                'person_id'        => $row['person_id'] ?: null,
                'full_name'        => mb_substr($row['full_name'] ?: 'Cliente', 0, 200),
                'document'         => $row['document'] ?: null,
                'email'            => $row['email'] ?: null,
                'phone'            => $row['phone'] ?: null,
                'dedupe_key'       => $row['dedupe_key'],
                'token'            => RaffleParticipant::makeToken(),
                'status'           => RaffleParticipant::STATUS_INVITED,
                'orders_count'     => $row['orders_count'],
                'total_amount'     => $row['total_amount'],
                'last_purchase_at' => $row['last_purchase_at'],
            ]);

            $created++;
        }

        return [
            'created'  => $created,
            'existing' => $existing->count(),
            'total'    => $raffle->participants()->count(),
        ];
    }

    // ── Internos ───────────────────────────────────────────────────────────

    /** Fuentes efectivas del sorteo (por defecto: comprobantes + notas de venta). */
    private function sourcesFor(Raffle $raffle): array
    {
        $sources = $raffle->sources;

        if (empty($sources)) {
            $sources = [Raffle::SOURCE_DOCUMENTS, Raffle::SOURCE_SALE_NOTES];
        }

        // Con filtro por producto/categoría los pedidos de tienda virtual
        // no se pueden evaluar (ítems en JSON) → se excluyen del universo.
        if ($this->hasProductFilter($raffle)) {
            $sources = array_values(array_diff($sources, [Raffle::SOURCE_ORDERS]));
        }

        return array_values(array_intersect($sources, array_keys(Raffle::SOURCES)));
    }

    public function hasProductFilter(Raffle $raffle): bool
    {
        return !empty($raffle->category_ids) || !empty($raffle->item_ids);
    }

    private function rowsFor(Raffle $raffle, string $source)
    {
        switch ($source) {
            case Raffle::SOURCE_DOCUMENTS:  return $this->documentRows($raffle);
            case Raffle::SOURCE_SALE_NOTES: return $this->saleNoteRows($raffle);
            case Raffle::SOURCE_ORDERS:     return $this->orderRows($raffle);
        }

        return collect();
    }

    /** Comprobantes (facturas / boletas). */
    private function documentRows(Raffle $raffle)
    {
        $query = Document::query()
            ->whereIn('state_type_id', self::VALID_STATE_TYPES)
            ->whereNotNull('customer_id');

        $this->applyDateRange($query, $raffle, 'date_of_issue');

        if ($raffle->establishment_id) {
            $query->where('establishment_id', $raffle->establishment_id);
        }

        if ($raffle->require_paid) {
            $query->where(function ($q) {
                $q->where('total_canceled', 1)
                  ->orWhereRaw('(select coalesce(sum(payment), 0) from document_payments
                                 where document_payments.document_id = documents.id) >= documents.total');
            });
        }

        $this->applyProductFilter($query, $raffle, 'document_items', 'document_id', 'documents');

        return $this->aggregateByCustomer($query, 'customer_id', 'date_of_issue');
    }

    /** Notas de venta. */
    private function saleNoteRows(Raffle $raffle)
    {
        $query = SaleNote::query()
            ->whereIn('state_type_id', self::VALID_STATE_TYPES)
            ->whereNotNull('customer_id');

        $this->applyDateRange($query, $raffle, 'date_of_issue');

        if ($raffle->establishment_id) {
            $query->where('establishment_id', $raffle->establishment_id);
        }

        if ($raffle->require_paid) {
            $query->where(function ($q) {
                $q->where('total_canceled', 1)
                  ->orWhereRaw('(select coalesce(sum(payment), 0) from sale_note_payments
                                 where sale_note_payments.sale_note_id = sale_notes.id) >= sale_notes.total');
            });
        }

        $this->applyProductFilter($query, $raffle, 'sale_note_items', 'sale_note_id', 'sale_notes');

        return $this->aggregateByCustomer($query, 'customer_id', 'date_of_issue');
    }

    /** Pedidos de tienda virtual. */
    private function orderRows(Raffle $raffle)
    {
        // `orders` no tiene date_of_issue: la fecha del pedido es created_at.
        $query = Order::query()->whereNotNull('person_id');

        $this->applyDateRange($query, $raffle, 'created_at');

        if ($raffle->channel_id) {
            $query->where('channel_id', $raffle->channel_id);
        }

        if ($raffle->require_paid) {
            // "Pago verificado" (status_order_id >= 2) o captura Culqi confirmada.
            $query->where(function ($q) {
                $q->where('status_order_id', '>=', 2)
                  ->orWhere('payment_status', 'captured');
            });
        }

        return $this->aggregateByCustomer($query, 'person_id', 'created_at');
    }

    /** Aplica el rango de fecha de compra si está configurado. */
    private function applyDateRange($query, Raffle $raffle, string $column): void
    {
        if ($raffle->purchase_from) {
            $query->whereDate($column, '>=', $raffle->purchase_from);
        }
        if ($raffle->purchase_to) {
            $query->whereDate($column, '<=', $raffle->purchase_to);
        }
    }

    /**
     * Restringe a pedidos que contengan al menos un ítem de las categorías o
     * de los productos configurados.
     */
    private function applyProductFilter($query, Raffle $raffle, string $itemsTable, string $fk, string $parentTable): void
    {
        if (!$this->hasProductFilter($raffle)) {
            return;
        }

        $categoryIds = array_filter((array) $raffle->category_ids);
        $itemIds     = array_filter((array) $raffle->item_ids);

        $query->whereExists(function ($q) use ($itemsTable, $fk, $parentTable, $categoryIds, $itemIds) {
            $q->select(DB::raw(1))
              ->from($itemsTable)
              ->whereColumn("{$itemsTable}.{$fk}", "{$parentTable}.id")
              ->where(function ($w) use ($itemsTable, $categoryIds, $itemIds) {
                  if (!empty($itemIds)) {
                      $w->orWhereIn("{$itemsTable}.item_id", $itemIds);
                  }
                  if (!empty($categoryIds)) {
                      $w->orWhereIn("{$itemsTable}.item_id", function ($sub) use ($categoryIds) {
                          $sub->select('id')->from('items')->whereIn('category_id', $categoryIds);
                      });
                  }
              });
        });
    }

    /**
     * Agrupa los pedidos por cliente y devuelve las filas normalizadas,
     * resolviendo nombre / documento / email / teléfono desde `persons`.
     */
    private function aggregateByCustomer($query, string $customerColumn, string $dateColumn)
    {
        $grouped = $query->selectRaw("{$customerColumn} as pid, count(*) as c, sum(total) as t, max({$dateColumn}) as last_date")
                         ->groupBy($customerColumn)
                         ->get();

        if ($grouped->isEmpty()) {
            return collect();
        }

        $people = Person::whereIn('id', $grouped->pluck('pid')->filter()->all())
                        ->get(['id', 'name', 'number', 'email', 'telephone'])
                        ->keyBy('id');

        return $grouped->map(function ($row) use ($people) {
            $person = $people->get($row->pid);

            $document = $person->number ?? null;
            $email    = $person->email ?? null;
            $phone    = $person->telephone ?? null;

            return [
                'person_id'        => (int) $row->pid,
                'full_name'        => $person->name ?? 'Cliente',
                'document'         => $document,
                'email'            => $email,
                'phone'            => $phone,
                'dedupe_key'       => RaffleParticipant::dedupeKeyFor($document, $email, $phone, (int) $row->pid),
                'orders_count'     => (int) $row->c,
                'total_amount'     => (float) $row->t,
                'last_purchase_at' => $row->last_date ? substr((string) $row->last_date, 0, 10) : null,
            ];
        })->filter(fn ($r) => $r['person_id'] > 0)->values();
    }
}
