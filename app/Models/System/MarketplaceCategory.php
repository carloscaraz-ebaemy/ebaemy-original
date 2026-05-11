<?php

namespace App\Models\System;

use App\Models\System\MarketplaceListing;
use Hyn\Tenancy\Traits\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Categoría oficial del marketplace ebaemy.
 *
 * Árbol n-ario administrado por el SuperAdmin. Se usa para:
 *   - Filtrar productos en /marketplace (selector jerárquico)
 *   - Generar URLs SEO inmutables (full_slug)
 *   - Ofrecer al seller la lista de categorías cuando publica un item
 *
 * NO confundir con `categories` (per-tenant), que cada tenant usa
 * libremente para organizar su catálogo interno.
 */
class MarketplaceCategory extends Model
{
    use UsesSystemConnection;

    protected $table = 'marketplace_categories';

    protected $fillable = [
        'parent_id',
        'level',
        'depth_path',
        'name',
        'slug',
        'full_slug',
        'icon',
        'image',
        'description',
        'is_active',
        'is_visible_in_marketplace',
        'is_leaf',
        'allow_seller_publish',
        'sort_order',
        'listings_count_cache',
    ];

    protected $casts = [
        'is_active'                 => 'boolean',
        'is_visible_in_marketplace' => 'boolean',
        'is_leaf'                   => 'boolean',
        'allow_seller_publish'      => 'boolean',
        'level'                     => 'integer',
        'sort_order'                => 'integer',
        'listings_count_cache'      => 'integer',
    ];

    // ─────────────────────────────────────────────────────────
    //  Relaciones
    // ─────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function listings(): HasMany
    {
        return $this->hasMany(MarketplaceListing::class, 'marketplace_category_id');
    }

    // ─────────────────────────────────────────────────────────
    //  Scopes
    // ─────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible_in_marketplace', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('sort_order');
    }

    public function scopeLeaves($query)
    {
        return $query->where('is_leaf', true);
    }

    public function scopePublishable($query)
    {
        return $query->where('allow_seller_publish', true);
    }

    // ─────────────────────────────────────────────────────────
    //  Jerarquía — helpers
    // ─────────────────────────────────────────────────────────

    /**
     * IDs de TODOS los descendientes (incluyendo a sí mismo).
     * Usa depth_path en lugar de recursividad SQL para performance.
     */
    public function descendantAndSelfIds(): array
    {
        $myPath = ($this->depth_path ?: '/') . $this->id . '/';

        $descIds = static::query()
            ->where('depth_path', 'like', $myPath . '%')
            ->pluck('id')
            ->toArray();

        return array_merge([$this->id], $descIds);
    }

    /**
     * Cadena de ancestros desde la raíz hasta este nodo (incluido).
     * Útil para breadcrumbs.
     *
     * @return Collection<MarketplaceCategory>
     */
    public function ancestorsAndSelf(): Collection
    {
        $ids = $this->ancestorIds();
        $ids[] = $this->id;

        if (empty($ids)) {
            return collect([$this]);
        }

        return static::query()
            ->whereIn('id', $ids)
            ->orderBy('level')
            ->get();
    }

    /**
     * Solo IDs de ancestros (sin self), parseando depth_path.
     * Si depth_path es "/1/4/15", retorna [1, 4, 15].
     */
    public function ancestorIds(): array
    {
        if (empty($this->depth_path)) {
            return [];
        }
        return array_values(array_filter(
            array_map('intval', explode('/', trim($this->depth_path, '/'))),
            fn ($id) => $id > 0
        ));
    }

    /**
     * Construye el árbol completo a partir de la raíz, eager-loaded.
     * Útil para selectores jerárquicos en formularios.
     *
     * @param  bool  $includeInactive  Si true, incluye también las is_active=false
     *                                 (necesario para el panel SuperAdmin donde se
     *                                 reactivan categorías).
     * @return Collection<MarketplaceCategory>  raíces con children pre-cargados
     */
    public static function tree(bool $includeInactive = false): Collection
    {
        $query = static::query()->orderBy('sort_order');
        if (!$includeInactive) {
            $query->active();
        }
        $all = $query->get();

        $byParent = $all->groupBy('parent_id');

        $attachChildren = function (MarketplaceCategory $node) use (&$attachChildren, $byParent) {
            $kids = $byParent->get($node->id, collect());
            $node->setRelation('children', $kids);
            foreach ($kids as $kid) {
                $attachChildren($kid);
            }
            return $node;
        };

        return $byParent->get(null, collect())->map($attachChildren);
    }

    // ─────────────────────────────────────────────────────────
    //  Mantenimiento de depth_path / full_slug / level / is_leaf
    // ─────────────────────────────────────────────────────────

    /**
     * Recalcula campos derivados (level, depth_path, full_slug) según parent.
     * Llamar antes de guardar cuando se cambia parent_id o slug.
     */
    public function refreshDerivedFields(): void
    {
        if ($this->parent_id) {
            $parent = $this->parent()->first();
            if (!$parent) {
                throw new \RuntimeException("Parent #{$this->parent_id} no existe");
            }
            $this->level      = $parent->level + 1;
            $this->depth_path = ($parent->depth_path ?: '/') . $parent->id . '/';
            $this->full_slug  = trim($parent->full_slug . '/' . $this->slug, '/');
        } else {
            $this->level      = 0;
            $this->depth_path = '/';
            $this->full_slug  = $this->slug;
        }
    }

    /**
     * Recalcula is_leaf de un padre cuando se inserta/elimina un hijo.
     */
    public static function refreshLeafFlag(?int $parentId): void
    {
        if (!$parentId) return;
        $hasChildren = static::query()->where('parent_id', $parentId)->exists();
        static::query()->where('id', $parentId)->update(['is_leaf' => !$hasChildren]);
    }

    // ─────────────────────────────────────────────────────────
    //  Booted: hooks para mantener invariantes
    // ─────────────────────────────────────────────────────────

    protected static function booted()
    {
        static::saving(function (MarketplaceCategory $cat) {
            // Normalizar slug si viene cambiado
            if ($cat->isDirty('name') && empty($cat->slug)) {
                $cat->slug = Str::slug($cat->name);
            }
            // Si parent_id cambió o es nuevo, recalcular derivados
            if ($cat->isDirty(['parent_id', 'slug']) || !$cat->exists) {
                $cat->refreshDerivedFields();
            }
        });

        static::created(function (MarketplaceCategory $cat) {
            self::refreshLeafFlag($cat->parent_id);
        });

        static::deleted(function (MarketplaceCategory $cat) {
            self::refreshLeafFlag($cat->parent_id);
        });
    }
}
