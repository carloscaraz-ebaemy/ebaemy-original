<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\System\MarketplaceCategory;
use App\Models\System\MarketplaceCategoryRequest;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Endpoints para que el panel del tenant pueda:
 *   1. Consultar el árbol oficial de categorías marketplace al editar
 *      un producto y elegir dónde publicarlo.
 *   2. Solicitar una nueva categoría cuando ninguna existente le sirve.
 *
 * Aunque las categorías viven en system DB, los modelos usan
 * UsesSystemConnection — Laravel resuelve la conexión correcta
 * automáticamente al hacer la query.
 *
 * Las consultas del árbol se cachean 30 min para evitar pegarle a la
 * BD en cada apertura de form de item (y se invalidan implícitamente
 * cuando el SuperAdmin modifica el árbol — TTL corto basta).
 */
class MarketplaceCategoryController extends Controller
{
    private const CACHE_KEY_TREE  = 'marketplace_categories_tree_v1';
    private const CACHE_KEY_FLAT  = 'marketplace_categories_flat_v1';
    private const CACHE_TTL_SECS  = 1800; // 30 min

    /**
     * Devuelve el árbol completo de categorías ACTIVAS para que el form
     * del item lo renderice como selector jerárquico. Se cachea.
     */
    public function tree(): JsonResponse
    {
        $tree = Cache::remember(self::CACHE_KEY_TREE, self::CACHE_TTL_SECS, function () {
            $cats = MarketplaceCategory::query()
                ->active()
                ->orderBy('sort_order')
                ->get([
                    'id', 'parent_id', 'name', 'slug', 'full_slug',
                    'level', 'icon', 'is_leaf', 'allow_seller_publish',
                ]);

            $byParent = $cats->groupBy('parent_id');

            $build = function ($parentId = null) use (&$build, $byParent) {
                return $byParent->get($parentId, collect())->map(function ($node) use ($build) {
                    return [
                        'id'                   => $node->id,
                        'name'                 => $node->name,
                        'slug'                 => $node->slug,
                        'full_slug'            => $node->full_slug,
                        'level'                => $node->level,
                        'icon'                 => $node->icon,
                        'is_leaf'              => (bool) $node->is_leaf,
                        'allow_seller_publish' => (bool) $node->allow_seller_publish,
                        'children'             => $build($node->id),
                    ];
                })->values()->all();
            };

            return $build(null);
        });

        return response()->json(['tree' => $tree]);
    }

    /**
     * Lista plana ordenada por full_slug — útil para selectores `<select>`
     * de fallback (no jerárquicos). Solo hojas publicables.
     */
    public function flat(): JsonResponse
    {
        $cats = Cache::remember(self::CACHE_KEY_FLAT, self::CACHE_TTL_SECS, function () {
            return MarketplaceCategory::query()
                ->active()
                ->visible()
                ->leaves()
                ->publishable()
                ->orderBy('full_slug')
                ->get(['id', 'name', 'full_slug', 'level', 'icon'])
                ->toArray();
        });

        return response()->json(['categories' => $cats]);
    }

    /**
     * Crea una solicitud de nueva categoría. El tenant llena el formulario
     * cuando no encuentra una categoría adecuada al publicar su item.
     */
    public function requestNew(Request $request): JsonResponse
    {
        $request->validate([
            'suggested_name'      => 'required|string|max:150',
            'suggested_parent_id' => 'nullable|integer|exists:marketplace_categories,id',
            'description'         => 'nullable|string|max:2000',
            'product_id'          => 'nullable|integer',
        ]);

        try {
            // Resolver tenant_id del Client desde el contexto Hyn
            $tenantId = $this->resolveTenantId();
            if (!$tenantId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo identificar el tenant.',
                ], 422);
            }

            // Anti-spam: no permitir duplicados pendientes con el mismo nombre
            $existing = MarketplaceCategoryRequest::query()
                ->where('tenant_id', $tenantId)
                ->where('status', MarketplaceCategoryRequest::STATUS_PENDING)
                ->whereRaw('LOWER(suggested_name) = ?', [strtolower($request->input('suggested_name'))])
                ->exists();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya tienes una solicitud pendiente con ese nombre. Espera la respuesta del equipo.',
                ], 422);
            }

            $req = MarketplaceCategoryRequest::create([
                'tenant_id'           => $tenantId,
                'user_id'             => auth()->id(),
                'product_id'          => $request->input('product_id'),
                'suggested_name'      => trim((string) $request->input('suggested_name')),
                'suggested_parent_id' => $request->input('suggested_parent_id'),
                'description'         => $request->input('description'),
                'status'              => MarketplaceCategoryRequest::STATUS_PENDING,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Solicitud enviada. Te avisaremos cuando el equipo de ebaemy la revise.',
                'id'      => $req->id,
            ]);
        } catch (Exception $e) {
            Log::error('MarketplaceCategoryController(tenant)::requestNew error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene el client.id del tenant actual leyendo Hyn Environment.
     * Devuelve null si no se pudo resolver (debería ser raro).
     */
    private function resolveTenantId(): ?int
    {
        try {
            $tenancy = app(\Hyn\Tenancy\Environment::class);
            $hostname = $tenancy->hostname();
            if (!$hostname) return null;

            $client = DB::connection('system')->table('clients')
                ->where('hostname_id', $hostname->id)
                ->select('id')
                ->first();

            return $client?->id;
        } catch (Exception $e) {
            Log::warning('resolveTenantId failed', ['e' => $e->getMessage()]);
            return null;
        }
    }
}
