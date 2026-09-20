<?php

namespace Modules\Item\Models;

use App\Models\Tenant\Item;
use App\Models\Tenant\ModelTenant;
use Illuminate\Support\Str;

class Category extends ModelTenant
{
    /** Nombre del cajón donde caen las categorías que nadie ha clasificado. */
    public const UNCLASSIFIED = 'Sin clasificar';

    protected $fillable = [
        'name',
        'image',
        'parent_id',
        'sort_order',
        'visible_ecommerce',
    ];

    protected $casts = [
        'visible_ecommerce' => 'boolean',
        'sort_order'        => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    // ── Jerarquía (dos niveles: padre → hija) ─────────────────────────────

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')
                    ->orderBy('sort_order')
                    ->orderBy('name');
    }

    public function isParent(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * La categoría propia más las de sus hijas.
     *
     * Filtrar por un padre tiene que traer lo que cuelga de él: si alguien
     * pincha «Electrónica y Audio» y sólo ve los productos asignados
     * literalmente a ese nombre —que suelen ser cero, porque los productos
     * viven en las hojas— la tienda parece vacía.
     */
    public function selfAndDescendantIds(): array
    {
        $ids = [$this->id];

        if ($this->isParent()) {
            $ids = array_merge($ids, self::where('parent_id', $this->id)->pluck('id')->all());
        }

        return $ids;
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('visible_ecommerce', true);
    }

    /** Orden del escaparate: primero lo colocado a mano, luego alfabético. */
    public function scopeOrdered($query)
    {
        return $query->orderByRaw('CASE WHEN sort_order = 0 THEN 1 ELSE 0 END')
                     ->orderBy('sort_order')
                     ->orderBy('name');
    }

    // ── Árbol para la tienda y el panel ───────────────────────────────────

    /**
     * Árbol de dos niveles con el número de productos de cada rama.
     *
     * Una sola consulta de categorías y una de conteo: el escaparate lo pinta
     * en cada carga y no puede permitirse una consulta por categoría.
     *
     * @param bool $onlyVisible   sólo las marcadas para la tienda
     * @param bool $onlyWithItems descarta las ramas sin un solo producto
     */
    public static function tree(bool $onlyVisible = true, bool $onlyWithItems = false): \Illuminate\Support\Collection
    {
        $query = self::query()->ordered();

        if ($onlyVisible) {
            $query->visible();
        }

        $all = $query->get();

        $counts = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table('items')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) AS total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        $byParent = $all->whereNotNull('parent_id')->groupBy('parent_id');

        $tree = $all->whereNull('parent_id')->map(function ($parent) use ($byParent, $counts) {
            $children = ($byParent[$parent->id] ?? collect())->map(function ($child) use ($counts) {
                $child->items_count = (int) ($counts[$child->id] ?? 0);
                return $child;
            })->values();

            $parent->children_list = $children;
            // El total del padre incluye lo suyo y lo de sus hijas: es el
            // número que el comprador espera ver junto al nombre del grupo.
            $parent->items_count = (int) ($counts[$parent->id] ?? 0) + $children->sum('items_count');

            return $parent;
        })->values();

        if ($onlyWithItems) {
            $tree = $tree->filter(fn ($p) => $p->items_count > 0)->values();
            $tree->each(function ($p) {
                $p->children_list = $p->children_list->filter(fn ($c) => $c->items_count > 0)->values();
            });
        }

        // Los padres se ordenan por relevancia (cuántos productos cuelgan de
        // ellos) salvo que el comerciante los haya colocado a mano.
        return $tree->sortBy(function ($p) {
            return [$p->sort_order === 0 ? 1 : 0, $p->sort_order ?: 0, -$p->items_count];
        })->values();
    }

    // ── Normalización de nombres ──────────────────────────────────────────

    /**
     * Limpia un nombre venido de la importación.
     *
     * Saga separa con barras verticales: «Cabello|coloraciones»,
     * «Parlantes|docks portátiles». Tentaba quedarse con el último trozo, pero
     * esa barra NO es una ruta de padre a hijo sino una enumeración, y
     * recortarla destroza el nombre: «Joyeros|bolsas» quedaba en «Bolsas» y
     * «Máquinas para hacer pasteles|postres|pies» en «Pies». Se conserva el
     * texto entero y sólo se cambia el separador por uno legible.
     *
     * El resto del texto se respeta — hay marcas y siglas que no se deben tocar.
     */
    public static function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            return $name;
        }

        if (Str::contains($name, ['|', '>'])) {
            $parts = array_filter(array_map('trim', preg_split('/\s*[|>]\s*/u', $name)));
            $name  = implode(' / ', $parts);
        }

        // TODO EN MAYÚSCULAS es grito, no nombre propio: se pasa a capitalizado.
        if ($name === mb_strtoupper($name, 'UTF-8') && mb_strlen($name) > 3) {
            $name = mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }

        return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($name, 1, null, 'UTF-8');
    }

    // ── Compatibilidad ────────────────────────────────────────────────────

    public function scopeFilterForTables($query)
    {
        return $query->select('id', 'name')->orderBy('name');
    }

    public function getRowResourceApi()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'selected' => false,
        ];
    }

    /**
     * Data para filtros - select
     *
     * @return array
     */
    public static function getDataForFilters()
    {
        return self::select(['id', 'name'])->orderBy('name')->get();
    }
}
