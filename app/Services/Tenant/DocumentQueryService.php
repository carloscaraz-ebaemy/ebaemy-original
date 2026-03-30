<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Document;
use Illuminate\Http\Request;

/**
 * Encapsula queries de listado/búsqueda de Documents.
 * Extraído de DocumentController para reducir God Controller.
 */
class DocumentQueryService
{
    /**
     * Build filtered query for document records.
     */
    public function getFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        $query = Document::with([
            'user', 'state_type', 'document_type', 'currency_type',
            'group', 'invoice', 'note', 'payments',
        ])->whereTypeUser();

        // Column search
        if ($request->filled('column') && $request->filled('value')) {
            $column = $request->column;
            $value = $request->value;

            if ($column === 'customer') {
                $query->whereHas('person', fn($q) => $q->where('name', 'like', "%{$value}%")
                    ->orWhere('number', 'like', "%{$value}%"));
            } else {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        // Date filter
        if ($request->filled('date_start') && $request->filled('date_end')) {
            $query->whereBetween('date_of_issue', [$request->date_start, $request->date_end]);
        }

        // State filter
        if ($request->filled('state_type_id')) {
            $query->where('state_type_id', $request->state_type_id);
        }

        // Document type filter
        if ($request->filled('document_type_id')) {
            $query->where('document_type_id', $request->document_type_id);
        }

        // Series filter
        if ($request->filled('series')) {
            $query->where('series', $request->series);
        }

        return $query->latest('id');
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
