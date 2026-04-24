<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreMarketplaceCategoryRequest;
use App\Http\Requests\System\UpdateMarketplaceCategoryRequest;
use App\Models\System\MarketplaceCategory;
use App\Models\System\MarketplaceListing;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Panel SuperAdmin para administrar el árbol oficial de categorías
 * del marketplace.
 *
 * Rutas (todas bajo auth:admin, prefix /admin/marketplace/categories):
 *   GET  /                 → vista del árbol
 *   GET  /tree             → árbol completo en JSON (para selector)
 *   GET  /records          → listado plano paginado JSON
 *   POST /                 → crear categoría
 *   PUT  /{id}             → actualizar
 *   DELETE /{id}           → eliminar (solo si no tiene hijos ni listings)
 *   POST /{id}/toggle      → toggle is_active / is_visible / allow_seller_publish
 *   POST /assign-bulk      → asignar marketplace_category_id a múltiples listings
 *
 * La lógica de jerarquía (level, depth_path, full_slug, is_leaf) la
 * mantiene el modelo via hooks Eloquent — el controller solo valida y
 * orquesta.
 */
class MarketplaceCategoryController extends Controller
{
    public function index()
    {
        return view('system.marketplace_categories.index');
    }

    /**
     * Árbol completo en JSON, eager-loaded en una sola query.
     * Usado por el selector jerárquico del seller (Fase C) y por el
     * panel SuperAdmin para visualizar el árbol.
     */
    public function tree(): JsonResponse
    {
        $tree = MarketplaceCategory::tree();
        return response()->json([
            'tree' => $this->serializeTree($tree),
        ]);
    }

    /**
     * Listado plano con búsqueda + filtros — para tabla del panel.
     */
    public function records(Request $request): JsonResponse
    {
        $q = MarketplaceCategory::query()->orderBy('full_slug');

        if ($search = trim((string) $request->input('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('name', 'like', "%{$search}%")
                   ->orWhere('full_slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('parent_id')) {
            $q->where('parent_id', $request->input('parent_id'));
        }

        if ($request->filled('is_active')) {
            $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = max(10, min((int) $request->input('per_page', 50), 200));
        $page = $q->paginate($perPage);

        return response()->json([
            'data'         => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
        ]);
    }

    public function store(StoreMarketplaceCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->ensureUniqueSlug($data['slug'] ?? null, $data['name'], $data['parent_id'] ?? null);

        // Defaults sensatos cuando vienen sin definir
        $data['is_active']                 = $data['is_active']                 ?? true;
        $data['is_visible_in_marketplace'] = $data['is_visible_in_marketplace'] ?? true;
        $data['allow_seller_publish']      = $data['allow_seller_publish']      ?? true;
        $data['sort_order']                = $data['sort_order']                ?? $this->nextSortOrder($data['parent_id'] ?? null);

        try {
            $category = MarketplaceCategory::create($data);
            return response()->json([
                'success'  => true,
                'message'  => 'Categoría creada.',
                'category' => $category->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function update(UpdateMarketplaceCategoryRequest $request, $id): JsonResponse
    {
        $category = MarketplaceCategory::query()->findOrFail($id);
        $data = $request->validated();

        if (array_key_exists('slug', $data)) {
            $data['slug'] = $this->ensureUniqueSlug(
                $data['slug'] ?? null,
                $data['name'] ?? $category->name,
                array_key_exists('parent_id', $data) ? $data['parent_id'] : $category->parent_id,
                $category->id
            );
        }

        // Validar que no se ponga como su propio descendiente (loop)
        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            if ($this->wouldCreateLoop($category, (int) $data['parent_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes mover una categoría dentro de sí misma o de uno de sus descendientes.',
                ], 422);
            }
        }

        try {
            DB::connection('system')->transaction(function () use ($category, $data) {
                $oldParent = $category->parent_id;
                $category->fill($data)->save();

                // Si cambió el parent o el slug, propagar full_slug y depth_path
                // a TODOS los descendientes (la categoría puede tener nietos).
                if ($category->wasChanged(['parent_id', 'slug'])) {
                    $this->propagateDerivedToDescendants($category);
                    if ($oldParent !== $category->parent_id) {
                        MarketplaceCategory::refreshLeafFlag($oldParent);
                    }
                }
            });

            return response()->json([
                'success'  => true,
                'message'  => 'Categoría actualizada.',
                'category' => $category->fresh(),
            ]);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Toggle inline de un flag boolean. Body acepta 'flag' con valor
     * is_active|is_visible_in_marketplace|allow_seller_publish.
     */
    public function toggle(Request $request, $id): JsonResponse
    {
        $request->validate([
            'flag' => 'required|in:is_active,is_visible_in_marketplace,allow_seller_publish',
        ]);
        $cat = MarketplaceCategory::query()->findOrFail($id);
        $field = $request->input('flag');
        $cat->{$field} = !$cat->{$field};
        $cat->save();

        return response()->json([
            'success'  => true,
            'message'  => 'Estado actualizado.',
            'value'    => (bool) $cat->{$field},
        ]);
    }

    /**
     * Elimina una categoría — solo si no tiene hijos NI listings asociados.
     * Si tiene listings, se requiere reasignarlos primero.
     */
    public function destroy($id): JsonResponse
    {
        $category = MarketplaceCategory::query()->findOrFail($id);

        if ($category->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar: tiene subcategorías. Mueve o elimina las hijas primero.',
            ], 422);
        }

        $listingsCount = MarketplaceListing::query()
            ->where('marketplace_category_id', $category->id)
            ->count();
        if ($listingsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar: {$listingsCount} producto(s) la usan. Reasígnalos a otra categoría primero.",
            ], 422);
        }

        $category->delete();
        return response()->json(['success' => true, 'message' => 'Categoría eliminada.']);
    }

    /**
     * Asigna marketplace_category_id a múltiples marketplace_listings en bloque.
     * Útil para clasificar el catálogo legacy que vino del modelo string.
     *
     * Body:
     *   listing_ids[]            int[]
     *   marketplace_category_id  int
     */
    public function assignBulk(Request $request): JsonResponse
    {
        $request->validate([
            'listing_ids'             => 'required|array|min:1|max:500',
            'listing_ids.*'           => 'integer',
            'marketplace_category_id' => 'required|integer|exists:marketplace_categories,id',
        ]);

        $cat = MarketplaceCategory::query()->findOrFail($request->input('marketplace_category_id'));

        $updated = MarketplaceListing::query()
            ->whereIn('id', $request->input('listing_ids'))
            ->update([
                'marketplace_category_id' => $cat->id,
                'category_name'           => $cat->name, // mantener legacy sincronizado
            ]);

        return response()->json([
            'success' => true,
            'message' => "Asignaste {$updated} producto(s) a la categoría '{$cat->full_slug}'.",
            'count'   => $updated,
        ]);
    }

    /**
     * Listado de listings sin marketplace_category_id (para asignación masiva).
     */
    public function unclassifiedListings(Request $request): JsonResponse
    {
        $q = MarketplaceListing::query()
            ->whereNull('marketplace_category_id')
            ->orderByDesc('id');

        if ($search = trim((string) $request->input('search', ''))) {
            $q->where(function ($qq) use ($search) {
                $qq->where('title', 'like', "%{$search}%")
                   ->orWhere('category_name', 'like', "%{$search}%");
            });
        }

        $perPage = max(10, min((int) $request->input('per_page', 30), 100));
        $page = $q->paginate($perPage);

        // Incluimos progreso de migración FK para que el panel muestre cuánto
        // falta — útil para decidir cuándo ejecutar la Fase E (dropear category_name).
        $migration = $this->migrationStats();

        return response()->json([
            'data'         => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
            'migration'    => $migration,
        ]);
    }

    /**
     * Endpoint dedicado para obtener el progreso de migración (sin paginación).
     * Lo usa el dashboard del marketplace central.
     */
    public function categoryMigrationStats(): JsonResponse
    {
        return response()->json($this->migrationStats());
    }

    /**
     * Calcula el estado de la migración Fase A-D → E:
     *   cuántos listings tienen la FK vs cuántos usan solo category_name legacy.
     */
    private function migrationStats(): array
    {
        $row = \DB::connection('system')->table('marketplace_listings')
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN marketplace_category_id IS NOT NULL THEN 1 ELSE 0 END) AS with_fk,
                SUM(CASE WHEN marketplace_category_id IS NULL AND category_name IS NOT NULL THEN 1 ELSE 0 END) AS legacy_only,
                SUM(CASE WHEN marketplace_category_id IS NULL AND category_name IS NULL THEN 1 ELSE 0 END) AS without_category
            ")
            ->first();

        $total = (int) ($row->total ?? 0);
        $withFk = (int) ($row->with_fk ?? 0);
        $pct = $total > 0 ? round(($withFk / $total) * 100, 1) : 0;

        return [
            'total'             => $total,
            'with_fk'           => $withFk,
            'legacy_only'       => (int) ($row->legacy_only ?? 0),
            'without_category'  => (int) ($row->without_category ?? 0),
            'fk_progress_pct'   => $pct,
            'ready_for_phase_e' => $pct >= 95.0 && $total > 0,
        ];
    }

    // ─────────────────────────────────────────────────────────
    //  Helpers privados
    // ─────────────────────────────────────────────────────────

    /**
     * Garantiza unicidad de slug bajo el mismo parent. Si el provisto choca,
     * añade sufijo numérico.
     */
    private function ensureUniqueSlug(?string $slug, string $name, ?int $parentId, ?int $ignoreId = null): string
    {
        $base = $slug ? Str::slug($slug) : Str::slug($name);
        if ($base === '') {
            $base = 'cat-' . now()->timestamp;
        }

        $candidate = $base;
        $i = 2;
        while ($this->slugExists($candidate, $parentId, $ignoreId)) {
            $candidate = $base . '-' . $i;
            $i++;
            if ($i > 100) break;
        }
        return $candidate;
    }

    private function slugExists(string $slug, ?int $parentId, ?int $ignoreId): bool
    {
        $q = MarketplaceCategory::query()
            ->where('slug', $slug)
            ->where(function ($qq) use ($parentId) {
                if ($parentId === null) {
                    $qq->whereNull('parent_id');
                } else {
                    $qq->where('parent_id', $parentId);
                }
            });
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        return $q->exists();
    }

    private function nextSortOrder(?int $parentId): int
    {
        return (int) MarketplaceCategory::query()
            ->where(function ($qq) use ($parentId) {
                $parentId === null ? $qq->whereNull('parent_id') : $qq->where('parent_id', $parentId);
            })
            ->max('sort_order') + 1;
    }

    private function wouldCreateLoop(MarketplaceCategory $cat, int $newParentId): bool
    {
        if ($newParentId === $cat->id) return true;
        return in_array($cat->id, MarketplaceCategory::query()->whereKey($newParentId)->first()?->ancestorIds() ?? [], true);
    }

    /**
     * Propaga full_slug y depth_path a todos los descendientes después
     * de un cambio de parent o slug. Usa el depth_path para encontrar
     * descendientes en una sola query y luego itera reasignando.
     */
    private function propagateDerivedToDescendants(MarketplaceCategory $root): void
    {
        $descendantIds = $root->descendantAndSelfIds();
        $descendantIds = array_diff($descendantIds, [$root->id]);

        if (empty($descendantIds)) return;

        // Cargamos en orden por level para procesar de arriba a abajo
        $nodes = MarketplaceCategory::query()
            ->whereIn('id', $descendantIds)
            ->orderBy('level')
            ->get()
            ->keyBy('id');

        foreach ($nodes as $node) {
            // Refresh recalcula a partir del parent (que pudo haber cambiado en
            // iteraciones anteriores si era ancestro)
            $node->refreshDerivedFields();
            $node->saveQuietly();
        }
    }

    /**
     * Convierte una colección de árbol Eloquent (ya con children eager-loaded
     * por el método tree()) a estructura JSON serializable.
     */
    private function serializeTree($collection): array
    {
        return $collection->map(function ($node) {
            return [
                'id'                        => $node->id,
                'parent_id'                 => $node->parent_id,
                'name'                      => $node->name,
                'slug'                      => $node->slug,
                'full_slug'                 => $node->full_slug,
                'level'                     => $node->level,
                'icon'                      => $node->icon,
                'is_active'                 => (bool) $node->is_active,
                'is_visible_in_marketplace' => (bool) $node->is_visible_in_marketplace,
                'is_leaf'                   => (bool) $node->is_leaf,
                'allow_seller_publish'      => (bool) $node->allow_seller_publish,
                'sort_order'                => (int) $node->sort_order,
                'listings_count_cache'      => (int) $node->listings_count_cache,
                'children'                  => $this->serializeTree($node->children ?? collect()),
            ];
        })->values()->all();
    }
}
