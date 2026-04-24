<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\ApproveCategoryRequestRequest;
use App\Http\Requests\System\RejectCategoryRequestRequest;
use App\Models\System\MarketplaceCategory;
use App\Models\System\MarketplaceCategoryRequest;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bandeja de solicitudes de nuevas categorías que los sellers crean
 * desde su panel cuando no encuentran una categoría adecuada al
 * publicar un producto.
 *
 * Rutas (auth:admin, prefix /admin/marketplace/category-requests):
 *   GET  /                    → vista bandeja
 *   GET  /records             → JSON paginado
 *   GET  /{id}                → detalle
 *   POST /{id}/approve        → crear marketplace_category + vincular request
 *   POST /{id}/reject         → rechazar con motivo
 */
class MarketplaceCategoryRequestController extends Controller
{
    public function index()
    {
        return view('system.marketplace_category_requests.index');
    }

    public function records(Request $request): JsonResponse
    {
        $q = MarketplaceCategoryRequest::query()->orderByDesc('id');

        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }

        if ($search = trim((string) $request->input('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('suggested_name', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = max(10, min((int) $request->input('per_page', 30), 100));
        $page = $q->paginate($perPage);

        return response()->json([
            'data'         => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
        ]);
    }

    public function show($id): JsonResponse
    {
        $req = MarketplaceCategoryRequest::query()
            ->with(['suggestedParent', 'resultingCategory'])
            ->findOrFail($id);

        // Lista de raíces para el selector "categoría padre" en el modal de aprobación
        $rootCategories = MarketplaceCategory::query()
            ->active()
            ->orderBy('full_slug')
            ->get(['id', 'name', 'full_slug', 'level']);

        return response()->json([
            'request'         => $req,
            'root_categories' => $rootCategories,
        ]);
    }

    public function approve(ApproveCategoryRequestRequest $request, $id): JsonResponse
    {
        $req = MarketplaceCategoryRequest::query()->findOrFail($id);

        if (!$req->isPending()) {
            return response()->json([
                'success' => false,
                'message' => "Solicitud no está pendiente (actual: {$req->status}).",
            ], 422);
        }

        $name     = $request->input('override_name')      ?: $req->suggested_name;
        $parentId = $request->input('override_parent_id') ?: $req->suggested_parent_id;

        try {
            $newCategory = DB::connection('system')->transaction(function () use ($req, $request, $name, $parentId) {
                $cat = MarketplaceCategory::create([
                    'parent_id'                 => $parentId,
                    'name'                      => $name,
                    'slug'                      => $this->uniqueSlugFor($name, $parentId),
                    'is_active'                 => true,
                    'is_visible_in_marketplace' => true,
                    'allow_seller_publish'      => true,
                    'sort_order'                => 999,
                ]);

                $req->update([
                    'status'                          => MarketplaceCategoryRequest::STATUS_APPROVED,
                    'admin_response'                  => $request->input('admin_response'),
                    'reviewed_by'                     => auth('admin')->id(),
                    'reviewed_at'                     => now(),
                    'created_marketplace_category_id' => $cat->id,
                ]);

                return $cat;
            });

            return response()->json([
                'success'  => true,
                'message'  => "Solicitud aprobada. Categoría '{$newCategory->full_slug}' creada.",
                'category' => $newCategory->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al aprobar: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function reject(RejectCategoryRequestRequest $request, $id): JsonResponse
    {
        $req = MarketplaceCategoryRequest::query()->findOrFail($id);

        if (!$req->isPending()) {
            return response()->json([
                'success' => false,
                'message' => "Solicitud no está pendiente (actual: {$req->status}).",
            ], 422);
        }

        $req->update([
            'status'         => MarketplaceCategoryRequest::STATUS_REJECTED,
            'admin_response' => $request->input('admin_response'),
            'reviewed_by'    => auth('admin')->id(),
            'reviewed_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Solicitud rechazada. El seller será notificado.',
        ]);
    }

    private function uniqueSlugFor(string $name, ?int $parentId): string
    {
        $base = Str::slug($name) ?: ('cat-' . now()->timestamp);
        $candidate = $base;
        $i = 2;
        while ($this->slugExists($candidate, $parentId)) {
            $candidate = $base . '-' . $i;
            $i++;
            if ($i > 100) break;
        }
        return $candidate;
    }

    private function slugExists(string $slug, ?int $parentId): bool
    {
        return MarketplaceCategory::query()
            ->where('slug', $slug)
            ->where(function ($qq) use ($parentId) {
                $parentId === null ? $qq->whereNull('parent_id') : $qq->where('parent_id', $parentId);
            })
            ->exists();
    }
}
