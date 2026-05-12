<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Amplía el árbol de categorías oficiales del marketplace.
 *
 * Agrega:
 *   - Hogar > Decoración → Alfombras y tapetes, Arreglos florales (las que el
 *     primer seller pidió en chat).
 *   - Hogar > Textil hogar (nueva sub) → Cortinas, Sábanas y edredones,
 *     Almohadones, Toallas.
 *   - Hogar > Jardín y plantas (sub nueva) con sus hojas.
 *   - Mascotas (raíz nueva) → Perros, Gatos, Aves, Peces y acuario,
 *     Roedores, Accesorios.
 *   - Libros, música y arte (raíz) → Libros, Música y vinilos, Arte y
 *     manualidades, Películas y series.
 *   - Automotor (raíz) → Repuestos, Llantas y aros, Aceites y fluidos,
 *     Accesorios, Sonido y multimedia.
 *   - Industria y oficina (raíz) → Papelería, Equipos de oficina, Muebles
 *     de oficina, Herramientas, Limpieza industrial.
 *   - Eventos y fiestas (raíz) → Decoración para eventos, Disfraces,
 *     Recordatorios, Globos y bombas.
 *   - Coleccionables y hobbies (raíz) → Stickers y cards, Figuras, Modelismo,
 *     Juegos de mesa.
 *   - Moda > Accesorios → Mochilas
 *   - Tecnología > Computación → Impresoras
 *   - Salud y bienestar (raíz nueva) → Suplementos, Aparatos médicos,
 *     Movilidad, Ortopedia. (Antes estaba como leaf bajo Belleza, ahora
 *     la dejamos también ahí PERO con árbol propio en raíz.)
 *   - Belleza → Manicure y pedicure, Depilación
 *   - Alimentos y bebidas → Café y té, Repostería casera, Sin gluten
 *   - Deportes y aire libre → Pesas y crossfit, Yoga y pilates, Running
 *
 * Estrategia idempotente: cada nodo se chequea por full_slug antes de
 * insertar. Si ya existe, no se duplica. Permite re-correr sin riesgo.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_categories')) return;

        $newTree = [
            // Hogar — ramas adicionales
            'Hogar' => [
                'Decoración' => [
                    'Alfombras y tapetes',
                    'Arreglos florales',
                    'Cortinas',
                ],
                'Textil hogar' => [
                    'Sábanas y edredones',
                    'Almohadones y cojines',
                    'Toallas',
                    'Manteles',
                ],
                'Jardín y plantas' => [
                    'Plantas naturales',
                    'Macetas y jardineras',
                    'Tierra y fertilizantes',
                    'Herramientas de jardín',
                ],
            ],

            // Moda — accesorios extras
            'Moda' => [
                'Accesorios' => [
                    'Mochilas',
                ],
            ],

            // Tecnología — añadir impresoras
            'Tecnología' => [
                'Computación' => [
                    'Impresoras y escáneres',
                ],
            ],

            // Belleza — más servicios/productos personales
            'Belleza' => [
                'Manicure y pedicure',
                'Depilación',
            ],

            // Deportes — disciplinas faltantes
            'Deportes y aire libre' => [
                'Pesas y crossfit',
                'Yoga y pilates',
                'Running',
            ],

            // Alimentos y bebidas — categorías que mueven en Perú
            'Alimentos y bebidas' => [
                'Café y té',
                'Repostería y panadería',
                'Sin gluten y vegano',
            ],
        ];

        // Raíces completamente nuevas
        $newRoots = [
            'Mascotas' => [
                'icon' => '🐾',
                'description' => 'Todo para tus mascotas: alimento, accesorios y cuidado.',
                'children' => [
                    'Perros' => ['Alimento', 'Camas y casetas', 'Juguetes', 'Correa y collar'],
                    'Gatos'  => ['Alimento', 'Arenas y sanitarios', 'Juguetes', 'Camas'],
                    'Aves',
                    'Peces y acuario',
                    'Roedores y conejos',
                    'Accesorios generales',
                ],
            ],
            'Libros, música y arte' => [
                'icon' => '📚',
                'description' => 'Libros, vinilos, arte y manualidades.',
                'children' => [
                    'Libros' => ['Ficción', 'No ficción', 'Infantil', 'Cómics y manga'],
                    'Música y vinilos',
                    'Arte y manualidades' => ['Pinturas y pinceles', 'Lienzos', 'Manualidades scrapbook'],
                    'Películas y series',
                    'Instrumentos musicales' => ['Guitarras', 'Teclados', 'Percusión', 'Vientos'],
                ],
            ],
            'Automotor' => [
                'icon' => '🚗',
                'description' => 'Repuestos, accesorios y mantenimiento de vehículos.',
                'children' => [
                    'Repuestos',
                    'Llantas y aros',
                    'Aceites y fluidos',
                    'Accesorios interior',
                    'Sonido y multimedia',
                    'Herramientas y emergencia',
                    'Motos y bicis' => ['Repuestos moto', 'Cascos y protección', 'Accesorios bici'],
                ],
            ],
            'Industria y oficina' => [
                'icon' => '🏢',
                'description' => 'Equipos, papelería y suministros para negocios.',
                'children' => [
                    'Papelería y escolar',
                    'Equipos de oficina',
                    'Muebles de oficina',
                    'Herramientas industriales',
                    'Limpieza industrial',
                    'EPP y seguridad',
                ],
            ],
            'Eventos y fiestas' => [
                'icon' => '🎉',
                'description' => 'Decoración, disfraces y todo lo necesario para tu evento.',
                'children' => [
                    'Decoración para eventos',
                    'Globos y bombas',
                    'Disfraces',
                    'Recordatorios y souvenirs',
                    'Vajilla descartable',
                ],
            ],
            'Coleccionables y hobbies' => [
                'icon' => '🎯',
                'description' => 'Productos para coleccionistas y aficionados.',
                'children' => [
                    'Stickers y cards',
                    'Figuras y action figures',
                    'Modelismo y maquetas',
                    'Juegos de mesa',
                    'Memorabilia deportiva',
                ],
            ],
            'Salud y bienestar' => [
                'icon' => '🩺',
                'description' => 'Suplementos, aparatos médicos y productos de bienestar.',
                'children' => [
                    'Suplementos y vitaminas',
                    'Aparatos médicos',
                    'Movilidad y ortopedia',
                    'Cuidado personal' => ['Higiene íntima', 'Cuidado dental'],
                    'Mascarillas y EPP personal',
                ],
            ],
        ];

        // Procesar extensiones (categorías hijas bajo raíces existentes)
        foreach ($newTree as $rootName => $children) {
            $root = $this->findBySlug(Str::slug($rootName));
            if (!$root) {
                // Si la raíz no existe (instancia sin seeder principal), saltar
                continue;
            }
            $this->seedChildren($root, $children);
        }

        // Procesar raíces nuevas
        $maxSort = (int) DB::table('marketplace_categories')->whereNull('parent_id')->max('sort_order');
        foreach ($newRoots as $rootName => $rootData) {
            $rootSlug = Str::slug($rootName);
            if ($this->findBySlug($rootSlug)) {
                // Ya existía (re-run); pasar a children por si hay nuevos
                $existingRoot = $this->findBySlug($rootSlug);
                $this->seedChildren($existingRoot, $rootData['children'] ?? []);
                continue;
            }
            $maxSort++;
            $rootNode = $this->createCategory(
                name: $rootName,
                parent: null,
                sort: $maxSort,
                icon: $rootData['icon'] ?? null,
                desc: $rootData['description'] ?? null,
            );
            $this->seedChildren($rootNode, $rootData['children'] ?? []);
        }
    }

    public function down(): void
    {
        // No-op: revertir esto borraría categorías que pueden tener listings
        // asignados → daño en cascada. Si necesitas removerlas, hacelo manual
        // desde /admin/marketplace/categories con el botón eliminar (que
        // valida que no haya listings activos antes de permitir el delete).
    }

    /**
     * Encuentra una categoría por su full_slug (que es único globalmente).
     * Devuelve un objeto-row o null.
     */
    private function findBySlug(string $fullSlug)
    {
        return DB::table('marketplace_categories')->where('full_slug', $fullSlug)->first();
    }

    /**
     * Recursive seed children — espera la misma estructura que MarketplaceCategoryTreeSeeder:
     *   - leaf simple: string
     *   - nodo con hijos: ['Nombre' => [...children]]
     */
    private function seedChildren($parent, array $children): void
    {
        $sort = (int) DB::table('marketplace_categories')->where('parent_id', $parent->id)->max('sort_order');

        foreach ($children as $key => $value) {
            if (is_int($key) && is_string($value)) {
                // Leaf simple
                $name = $value;
                $slug = Str::slug($name);
                $fullSlug = trim($parent->full_slug . '/' . $slug, '/');
                if ($this->findBySlug($fullSlug)) continue;
                $sort++;
                $this->createCategory($name, $parent, $sort);
            } else {
                // Nodo con possible children
                $name = is_string($key) ? $key : (is_array($value) && isset($value['name']) ? $value['name'] : null);
                if (!$name) continue;
                $slug = Str::slug($name);
                $fullSlug = trim($parent->full_slug . '/' . $slug, '/');
                $existing = $this->findBySlug($fullSlug);
                if ($existing) {
                    if (is_array($value)) {
                        // El value array es la lista de children (no ['children' => ...])
                        $this->seedChildren($existing, $value);
                    }
                    continue;
                }
                $sort++;
                $node = $this->createCategory($name, $parent, $sort);
                if (is_array($value)) {
                    $this->seedChildren($node, $value);
                }
            }
        }
    }

    private function createCategory(string $name, $parent, int $sort, ?string $icon = null, ?string $desc = null)
    {
        $slug = Str::slug($name);
        $fullSlug = $parent ? trim($parent->full_slug . '/' . $slug, '/') : $slug;
        $level = $parent ? ($parent->level + 1) : 0;
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
            'is_visible_in_marketplace' => 1,
            'is_leaf'                   => 1,
            'allow_seller_publish'      => 1,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        // Si tiene parent, marcar el parent como NO leaf (ya tiene hijos)
        if ($parent) {
            DB::table('marketplace_categories')->where('id', $parent->id)->update(['is_leaf' => 0]);
        }

        // Devolver objeto con los campos que seedChildren necesita
        return (object) [
            'id'         => $id,
            'parent_id'  => $parent ? $parent->id : null,
            'level'      => $level,
            'depth_path' => $depthPath,
            'full_slug'  => $fullSlug,
        ];
    }
};
