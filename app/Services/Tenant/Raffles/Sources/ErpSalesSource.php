<?php

namespace App\Services\Tenant\Raffles\Sources;

use App\Models\Tenant\Document;
use App\Models\Tenant\Raffle;
use App\Models\Tenant\SaleNote;
use App\Services\Tenant\Raffles\ParticipantSource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Base de los orígenes que leen las ventas del ERP (`documents` y
 * `sale_notes`): Gestión de Pedidos y Ventas Mostrador comparten toda la
 * mecánica y solo difieren en el `restrict()` que aplica cada uno.
 */
abstract class ErpSalesSource extends ParticipantSource
{
    /** Estados de comprobante que cuentan como venta válida (no anulada/rechazada). */
    protected const VALID_STATE_TYPES = ['01', '03', '05', '07'];

    /** Gancho para que cada origen acote el universo (p. ej. solo ventas de caja). */
    protected function restrict($query, string $table): void
    {
        // Por defecto no acota nada.
    }

    public function resolve(Raffle $raffle): Collection
    {
        return $this->salesRows($raffle, Document::query(), 'documents', 'document_payments', 'document_id')
            ->concat($this->salesRows($raffle, SaleNote::query(), 'sale_notes', 'sale_note_payments', 'sale_note_id'));
    }

    protected function salesRows(Raffle $raffle, $query, string $table, string $paymentsTable, string $paymentsFk): Collection
    {
        $states = $this->arrayFilter($raffle, 'state_types') ?: self::VALID_STATE_TYPES;

        $query->whereIn('state_type_id', $states)->whereNotNull('customer_id');

        $this->applyDates($query, $raffle, 'date_of_issue');
        $this->restrict($query, $table);

        if ($this->boolFilter($raffle, 'paid', true)) {
            $query->where(function ($q) use ($table, $paymentsTable, $paymentsFk) {
                $q->where('total_canceled', 1)
                  ->orWhereRaw("(select coalesce(sum(payment), 0) from {$paymentsTable}
                                 where {$paymentsTable}.{$paymentsFk} = {$table}.id) >= {$table}.total");
            });
        }

        if ($establishment = $this->filter($raffle, 'establishment_id')) {
            $query->where('establishment_id', $establishment);
        }

        if ($min = (float) $this->filter($raffle, 'min_ticket', 0)) {
            $query->where('total', '>=', $min);
        }

        $this->applyProductFilter($query, $raffle, $table);

        return $this->groupByPerson($query, 'customer_id', 'date_of_issue');
    }

    /** Restringe a ventas que contengan alguna categoría / producto elegido. */
    protected function applyProductFilter($query, Raffle $raffle, string $parentTable): void
    {
        $categories = $this->arrayFilter($raffle, 'categories');
        $items      = $this->arrayFilter($raffle, 'items');

        if (empty($categories) && empty($items)) {
            return;
        }

        $itemsTable = $parentTable === 'documents' ? 'document_items' : 'sale_note_items';
        $fk         = $parentTable === 'documents' ? 'document_id' : 'sale_note_id';

        $query->whereExists(function ($q) use ($itemsTable, $fk, $parentTable, $categories, $items) {
            $q->select(DB::raw(1))
              ->from($itemsTable)
              ->whereColumn("{$itemsTable}.{$fk}", "{$parentTable}.id")
              ->where(function ($w) use ($itemsTable, $categories, $items) {
                  if (!empty($items)) {
                      $w->orWhereIn("{$itemsTable}.item_id", $items);
                  }
                  if (!empty($categories)) {
                      $w->orWhereIn("{$itemsTable}.item_id", function ($sub) use ($categories) {
                          $sub->select('id')->from('items')->whereIn('category_id', $categories);
                      });
                  }
              });
        });
    }

    /** Filtros comunes a los orígenes de venta del ERP. */
    protected function salesFilters(): array
    {
        return [
            [
                'key' => 'paid', 'type' => 'boolean', 'default' => true,
                'label' => 'Solo con pago confirmado',
                'help'  => 'Comprobante marcado como cancelado o con pagos que cubren el total.',
            ],
            ['key' => 'date_from', 'type' => 'date', 'label' => 'Compras desde'],
            ['key' => 'date_to',   'type' => 'date', 'label' => 'Compras hasta'],
            [
                'key' => 'state_types', 'type' => 'multiselect',
                'label'   => 'Estado del documento',
                'options' => ['01' => 'Registrado', '03' => 'Enviado', '05' => 'Aceptado', '07' => 'Observado'],
                'help'    => 'Vacío = todos los estados válidos (excluye anulados y rechazados).',
            ],
            ['key' => 'establishment_id', 'type' => 'select', 'label' => 'Sucursal', 'options' => 'establishments'],
            ['key' => 'min_ticket', 'type' => 'number', 'label' => 'Monto mínimo por venta'],
            ['key' => 'categories', 'type' => 'multiselect', 'label' => 'Categorías compradas', 'options' => 'categories'],
            ['key' => 'items', 'type' => 'items', 'label' => 'Productos comprados'],
        ];
    }
}
