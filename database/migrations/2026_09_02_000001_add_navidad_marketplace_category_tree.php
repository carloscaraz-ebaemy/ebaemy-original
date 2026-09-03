<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Crea la rama de campaña "Navidad" en el árbol oficial del marketplace.
 *
 * Estructura (Alternativa C de la auditoría del 2026-09-02): raíz propia con
 * subcategorías internas, en lugar de colgarla de Hogar > Decoración. El menú
 * público solo carga raíces con sus hijos inmediatos
 * (MarketplaceController::getOfficialRootsCached), así que una campaña anidada
 * a nivel 2 quedaría invisible justo cuando debe ser lo más visible del sitio.
 *
 *   Navidad
 *   ├── Árboles de Navidad
 *   ├── Adornos y esferas
 *   ├── Luces navideñas
 *   ├── Guirnaldas
 *   ├── Coronas
 *   ├── Nacimientos
 *   ├── Decoración navideña para el hogar
 *   └── Otros productos navideños
 *
 * ── NACE OCULTA, A PROPÓSITO ────────────────────────────────────────────────
 * Toda la rama se crea con is_visible_in_marketplace = 0. El menú del
 * marketplace muestra las raíces sin importar cuántos productos tengan, así
 * que publicarla vacía dejaría una categoría de campaña con cero resultados a
 * la vista de todos. Oculta sigue siendo 100% asignable: el selector del form
 * de item (MarketplaceCategoryController@tree) filtra solo por is_active, no
 * por visibilidad — los sellers ya pueden categorizar su stock navideño.
 *
 * Para publicarla cuando haya productos, desde /admin/marketplace/categories
 * marcar visible la raíz y sus 8 hijas (o el UPDATE equivalente sobre
 * depth_path). Ahí mismo conviene subirle el sort_order: esta migración la
 * agrega al final (sort_order 15) para no reordenar las 15 raíces existentes,
 * pero en campaña debe ir primera.
 *
 * Idempotente: cada nodo se busca por full_slug antes de insertar, así que
 * re-correrla no duplica nada y completa lo que falte.
 */
return new class extends Migration {
    /** Nombre y metadatos de la raíz de campaña. */
    private const ROOT_NAME = 'Navidad';
    private const ROOT_ICON = '🎄';
    private const ROOT_DESC = 'Árboles, adornos, luces y todo para decorar tu Navidad.';

    /** Subcategorías de la rama, en el orden en que deben mostrarse. */
    private const CHILDREN = [
        'Árboles de Navidad',
        'Adornos y esferas',
        'Luces navideñas',
        'Guirnaldas',
        'Coronas',
        'Nacimientos',
        'Decoración navideña para el hogar',
        'Otros productos navideños',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('marketplace_categories')) return;

        $rootSlug = Str::slug(self::ROOT_NAME);
        $root     = $this->findBySlug($rootSlug);

        if (!$root) {
            $maxSort = (int) DB::table('marketplace_categories')
                ->whereNull('parent_id')
                ->max('sort_order');

            $root = $this->createCategory(
                name:   self::ROOT_NAME,
                parent: null,
                sort:   $maxSort + 1,
                icon:   self::ROOT_ICON,
                desc:   self::ROOT_DESC,
            );
        }

        $this->seedChildren($root, self::CHILDREN);
    }

    public function down(): void
    {
        // No-op deliberado: borrar categorías que ya pueden tener listings
        // asignados los dejaría huérfanos. Para removerlas, usar el botón
        // eliminar de /admin/marketplace/categories, que valida que no haya
        // listings activos antes de permitir el delete.
    }

    /** El full_slug es único globalmente — sirve de clave natural. */
    private function findBySlug(string $fullSlug)
    {
        return DB::table('marketplace_categories')->where('full_slug', $fullSlug)->first();
    }

    /**
     * Inserta las hojas que falten bajo $parent, respetando el orden del array
     * y continuando la numeración de sort_order que ya tenga el padre.
     */
    private function seedChildren($parent, array $children): void
    {
        $sort = (int) DB::table('marketplace_categories')
            ->where('parent_id', $parent->id)
            ->max('sort_order');

        foreach ($children as $name) {
            $fullSlug = trim($parent->full_slug . '/' . Str::slug($name), '/');
            if ($this->findBySlug($fullSlug)) continue;

            $sort++;
            $this->createCategory($name, $parent, $sort);
        }
    }

    private function createCategory(string $name, $parent, int $sort, ?string $icon = null, ?string $desc = null)
    {
        $slug      = Str::slug($name);
        $fullSlug  = $parent ? trim($parent->full_slug . '/' . $slug, '/') : $slug;
        $level     = $parent ? ($parent->level + 1) : 0;
        $depthPath = $parent ? (($parent->depth_path ?: '/') . $parent->id . '/') : '/';

        $id = DB::table('marketplace_categories')->insertGetId([
            'parent_id'                 => $parent ? $parent->id : null,
            'level'                     => $level,
            'depth_path'                => $depthPath,
            'name'                      => $name,
            'slug'                      => $slug,
            'full_slug'                 => $fullSlug,
            'icon'                      => $icon,
            'description'               => $desc,
            'sort_order'                => $sort,
            'is_active'                 => 1,
            // Ver cabecera: la rama nace oculta del público pero asignable.
            'is_visible_in_marketplace' => 0,
            'is_leaf'                   => 1,
            'allow_seller_publish'      => 1,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        if ($parent) {
            DB::table('marketplace_categories')->where('id', $parent->id)->update(['is_leaf' => 0]);
        }

        return (object) [
            'id'         => $id,
            'parent_id'  => $parent ? $parent->id : null,
            'level'      => $level,
            'depth_path' => $depthPath,
            'full_slug'  => $fullSlug,
        ];
    }
};
