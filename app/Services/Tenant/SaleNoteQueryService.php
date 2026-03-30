<?php

namespace App\Services\Tenant;

use App\Models\Tenant\SaleNote;
use Illuminate\Http\Request;

/**
 * Encapsula las queries de listado/búsqueda de SaleNotes.
 * Extraído de SaleNoteController para reducir su tamaño.
 */
class SaleNoteQueryService
{
    /**
     * Build filtered query for sale note records.
     */
    public function getFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $records = SaleNote::with(['documents', 'person'])->whereTypeUser();

        // Suscripciones/matrículas
        if ($request->boolean('onlySuscription')) {
            $records->whereNotNull('grade')->whereNotNull('section');
        }
        if ($request->boolean('onlyFullSuscription')) {
            $records->whereNotNull('user_rel_suscription_plan_id')
                ->whereNull('grade')->whereNull('section');
        }

        // Búsqueda por columna
        if ($request->column === 'customer') {
            $records->whereHas('person', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->value}%")
                      ->orWhere('number', 'like', "%{$request->value}%");
            })->latest();
        } else {
            $records->where($request->column, 'like', "%{$request->value}%")
                    ->latest('id');
        }

        // Filtros adicionales
        if ($request->filled('series')) {
            $records->where('series', 'like', '%' . $request->series . '%');
        }
        if ($request->filled('number')) {
            $records->where('number', 'like', '%' . $request->number . '%');
        }
        if ($request->has('total_canceled') && $request->total_canceled !== null) {
            $records->where('total_canceled', $request->total_canceled);
        }
        if ($request->filled('purchase_order')) {
            $records->where('purchase_order', $request->purchase_order);
        }
        if ($request->filled('license_plate')) {
            $records->where('license_plate', $request->license_plate);
        }
        if ($request->filled('observations')) {
            $records->where('observation', 'like', '%' . $request->observations . '%');
        }

        return $records;
    }

    /**
     * Get paginated records.
     */
    public function getPaginatedRecords(Request $request, ?int $perPage = null)
    {
        $perPage = $perPage ?? config('tenant.items_per_page', 20);
        return $this->getFilteredQuery($request)->paginate($perPage);
    }
}
