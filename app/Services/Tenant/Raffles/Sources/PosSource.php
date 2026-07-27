<?php

namespace App\Services\Tenant\Raffles\Sources;

use Illuminate\Support\Facades\DB;

/**
 * Ventas Mostrador (POS): las ventas del ERP que pasaron por una caja.
 *
 * El marcador es `cash_documents`, la tabla puente que liga cada caja con el
 * comprobante o la nota de venta que emitió — es lo que distingue una venta
 * de mostrador de una venta administrativa o de tienda virtual.
 */
class PosSource extends ErpSalesSource
{
    public function key(): string
    {
        return 'pos';
    }

    public function label(): string
    {
        return 'Ventas Mostrador (POS)';
    }

    public function description(): string
    {
        return 'Clientes atendidos en caja (ventas ligadas a una apertura de caja).';
    }

    public function icon(): string
    {
        return '🏪';
    }

    public function available(): bool
    {
        return $this->tableExists('cash_documents');
    }

    public function unavailableReason(): string
    {
        return 'Tu tienda no tiene el módulo de cajas / punto de venta.';
    }

    protected function restrict($query, string $table): void
    {
        $column = $table === 'documents' ? 'document_id' : 'sale_note_id';

        $query->whereExists(function ($q) use ($table, $column) {
            $q->select(DB::raw(1))
              ->from('cash_documents')
              ->whereColumn("cash_documents.{$column}", "{$table}.id");
        });
    }

    public function filters(): array
    {
        // El POS no filtra por producto: en mostrador interesa la caja y la
        // sucursal, no la canasta. Se deja el resto del set común.
        return array_values(array_filter(
            $this->salesFilters(),
            fn ($f) => !in_array($f['key'], ['categories', 'items'], true)
        ));
    }
}
