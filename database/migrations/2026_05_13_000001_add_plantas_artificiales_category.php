<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Añade la hoja "Plantas artificiales" bajo Hogar > Decoración.
 *
 * Motivo: las plantas colgantes / artificiales son un producto popular
 * en el marketplace y conviene tener su propia hoja para SEO (la gente
 * busca exactamente "plantas artificiales") y para filtros del marketplace.
 *
 * Idempotente: si ya existe el full_slug, no hace nada.
 */
return new class extends Migration {
    public function up(): void
    {
        $parent = DB::table('marketplace_categories')
            ->where('full_slug', 'hogar/decoracion')
            ->first();

        if (!$parent) return; // marketplace_categories aún no seedeado

        $fullSlug = 'hogar/decoracion/plantas-artificiales';
        if (DB::table('marketplace_categories')->where('full_slug', $fullSlug)->exists()) {
            return;
        }

        $sort = (int) DB::table('marketplace_categories')
            ->where('parent_id', $parent->id)
            ->max('sort_order');

        $depthPath = ($parent->depth_path ?: '/') . $parent->id . '/';

        DB::table('marketplace_categories')->insert([
            'parent_id'                 => $parent->id,
            'level'                     => ($parent->level ?? 1) + 1,
            'depth_path'                => $depthPath,
            'name'                      => 'Plantas artificiales',
            'slug'                      => 'plantas-artificiales',
            'full_slug'                 => $fullSlug,
            'icon'                      => '🪴',
            'description'               => 'Plantas decorativas artificiales: colgantes, en maceta, suculentas y flores artificiales para hogar y oficina.',
            'sort_order'                => $sort + 1,
            'is_active'                 => 1,
            'is_visible_in_marketplace' => 1,
            'is_leaf'                   => 1,
            'allow_seller_publish'      => 1,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        // Si el parent estaba marcado como leaf, dejarlo en no-leaf.
        DB::table('marketplace_categories')->where('id', $parent->id)->update(['is_leaf' => 0]);
    }

    public function down(): void
    {
        // No-op: ver nota en la migración del árbol expandido (puede haber
        // listings ya asignados). Eliminar manual desde /admin/marketplace/categories.
    }
};
