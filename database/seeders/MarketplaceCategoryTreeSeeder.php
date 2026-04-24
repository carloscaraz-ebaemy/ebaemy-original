<?php

namespace Database\Seeders;

use App\Models\System\MarketplaceCategory;
use Illuminate\Database\Seeder;

/**
 * Árbol inicial de categorías oficiales del marketplace.
 *
 * Cubre las 8 macro-categorías más relevantes para retail peruano,
 * con 1-2 niveles de subcategoría que cubren el grueso de productos
 * que los tenants probablemente publiquen.
 *
 * El SuperAdmin puede ampliar/refinar el árbol después desde
 * /admin/marketplace/categories. Este seed es el punto de partida —
 * es idempotente: si una categoría ya existe (por full_slug) no la
 * duplica.
 *
 * Ejecutar con:
 *   php artisan db:seed --class=MarketplaceCategoryTreeSeeder
 */
class MarketplaceCategoryTreeSeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            // ── HOGAR ─────────────────────────────────────────
            'Hogar' => [
                'icon' => '🏠',
                'description' => 'Todo para tu casa: muebles, decoración, cocina, jardín.',
                'children' => [
                    'Decoración' => [
                        'children' => [
                            'Plantas artificiales',
                            'Cuadros y láminas',
                            'Espejos',
                            'Floreros y jarrones',
                            'Velas y aromaterapia',
                        ],
                    ],
                    'Muebles' => [
                        'children' => [
                            'Sala',
                            'Comedor',
                            'Dormitorio',
                            'Oficina',
                            'Auxiliares',
                        ],
                    ],
                    'Cocina y mesa' => [
                        'children' => [
                            'Vajilla',
                            'Cubiertos',
                            'Ollas y sartenes',
                            'Pequeños electrodomésticos',
                            'Organización',
                        ],
                    ],
                    'Iluminación' => [
                        'children' => [
                            'Lámparas de techo',
                            'Lámparas de pie',
                            'Lámparas de mesa',
                            'Iluminación LED',
                        ],
                    ],
                    'Baño',
                    'Jardín y exterior',
                    'Limpieza',
                ],
            ],

            // ── MODA ─────────────────────────────────────────
            'Moda' => [
                'icon' => '👕',
                'description' => 'Ropa, calzado y accesorios para mujer, hombre y niños.',
                'children' => [
                    'Mujer' => [
                        'children' => [
                            'Polos y blusas',
                            'Vestidos',
                            'Pantalones y jeans',
                            'Faldas',
                            'Abrigos y casacas',
                            'Ropa interior',
                            'Ropa deportiva',
                        ],
                    ],
                    'Hombre' => [
                        'children' => [
                            'Polos y camisas',
                            'Pantalones y jeans',
                            'Casacas y abrigos',
                            'Ropa interior',
                            'Ropa deportiva',
                        ],
                    ],
                    'Niños' => [
                        'children' => [
                            'Niñas',
                            'Niños',
                            'Bebés',
                        ],
                    ],
                    'Calzado' => [
                        'children' => [
                            'Zapatillas',
                            'Zapatos',
                            'Sandalias y ojotas',
                            'Botas y botines',
                        ],
                    ],
                    'Accesorios' => [
                        'children' => [
                            'Bolsos y carteras',
                            'Billeteras',
                            'Cinturones',
                            'Relojes',
                            'Bisutería',
                            'Lentes de sol',
                            'Sombreros y gorros',
                        ],
                    ],
                ],
            ],

            // ── TECNOLOGÍA ───────────────────────────────────
            'Tecnología' => [
                'icon' => '💻',
                'description' => 'Celulares, computación, audio, video y accesorios.',
                'children' => [
                    'Celulares' => [
                        'children' => [
                            'Smartphones',
                            'Fundas y protectores',
                            'Cargadores y cables',
                            'Power banks',
                        ],
                    ],
                    'Computación' => [
                        'children' => [
                            'Laptops',
                            'PC de escritorio',
                            'Tablets',
                            'Monitores',
                            'Teclados y mouse',
                            'Discos y almacenamiento',
                        ],
                    ],
                    'Audio' => [
                        'children' => [
                            'Audífonos',
                            'Parlantes bluetooth',
                            'Equipos de sonido',
                            'Micrófonos',
                        ],
                    ],
                    'Video y TV' => [
                        'children' => [
                            'Smart TV',
                            'Cámaras',
                            'Proyectores',
                        ],
                    ],
                    'Gaming' => [
                        'children' => [
                            'Consolas',
                            'Videojuegos',
                            'Accesorios gaming',
                        ],
                    ],
                ],
            ],

            // ── BELLEZA ──────────────────────────────────────
            'Belleza' => [
                'icon' => '💄',
                'description' => 'Cuidado personal, maquillaje y bienestar.',
                'children' => [
                    'Maquillaje' => [
                        'children' => [
                            'Rostro',
                            'Ojos',
                            'Labios',
                            'Brochas y pinceles',
                        ],
                    ],
                    'Cuidado de la piel' => [
                        'children' => [
                            'Cremas faciales',
                            'Cremas corporales',
                            'Limpiadores',
                            'Protector solar',
                        ],
                    ],
                    'Cuidado del cabello' => [
                        'children' => [
                            'Shampoo y acondicionador',
                            'Tratamientos',
                            'Tintes',
                            'Herramientas',
                        ],
                    ],
                    'Perfumería',
                    'Salud y bienestar',
                ],
            ],

            // ── NIÑOS Y BEBÉS ───────────────────────────────
            'Niños y bebés' => [
                'icon' => '👶',
                'description' => 'Productos para los más pequeños.',
                'children' => [
                    'Pañales y cuidado',
                    'Alimentación',
                    'Juguetes' => [
                        'children' => [
                            'Educativos',
                            'Muñecas y peluches',
                            'Vehículos',
                            'Juegos de mesa',
                            'Aire libre',
                        ],
                    ],
                    'Coches y sillas',
                    'Cuna y dormitorio',
                ],
            ],

            // ── DEPORTES ─────────────────────────────────────
            'Deportes y aire libre' => [
                'icon' => '⚽',
                'description' => 'Equipamiento deportivo y outdoor.',
                'children' => [
                    'Indumentaria deportiva',
                    'Fútbol',
                    'Fitness y gimnasio',
                    'Ciclismo',
                    'Camping y outdoor',
                    'Natación',
                ],
            ],

            // ── ALIMENTOS ────────────────────────────────────
            'Alimentos y bebidas' => [
                'icon' => '🛒',
                'description' => 'Abarrotes, bebidas y productos gourmet.',
                'children' => [
                    'Abarrotes',
                    'Bebidas',
                    'Snacks y dulces',
                    'Productos gourmet',
                    'Saludables y orgánicos',
                ],
            ],

            // ── SERVICIOS ────────────────────────────────────
            'Servicios' => [
                'icon' => '🛠️',
                'description' => 'Servicios profesionales y para empresas.',
                'children' => [
                    'Profesionales',
                    'Eventos y catering',
                    'Mantenimiento',
                    'Diseño y marketing',
                ],
            ],
        ];

        $sortRoot = 0;
        foreach ($tree as $rootName => $rootData) {
            $root = $this->createOrFindCategory(
                name:   $rootName,
                parent: null,
                sort:   $sortRoot++,
                icon:   $rootData['icon']        ?? null,
                desc:   $rootData['description'] ?? null,
            );
            $this->seedChildren($root, $rootData['children'] ?? []);
        }
    }

    /**
     * Recursivo: el árbol del seeder admite hojas como string puro
     * o nodos asociativos con claves 'children'/'icon'/'description'.
     */
    private function seedChildren(MarketplaceCategory $parent, array $children): void
    {
        $sort = 0;
        foreach ($children as $key => $value) {
            if (is_int($key) && is_string($value)) {
                // Hoja simple: solo nombre
                $this->createOrFindCategory($value, $parent, $sort++);
            } else {
                // Nodo con posible children/icon/description
                $name = is_string($key) ? $key : ($value['name'] ?? null);
                if (!$name) continue;
                $node = $this->createOrFindCategory(
                    name:   $name,
                    parent: $parent,
                    sort:   $sort++,
                    icon:   $value['icon']        ?? null,
                    desc:   $value['description'] ?? null,
                );
                if (!empty($value['children']) && is_array($value['children'])) {
                    $this->seedChildren($node, $value['children']);
                }
            }
        }
    }

    private function createOrFindCategory(
        string $name,
        ?MarketplaceCategory $parent,
        int $sort,
        ?string $icon = null,
        ?string $desc = null,
    ): MarketplaceCategory {
        $slug = \Illuminate\Support\Str::slug($name);
        $fullSlug = $parent ? trim($parent->full_slug . '/' . $slug, '/') : $slug;

        // Idempotencia: buscar por full_slug
        $existing = MarketplaceCategory::query()->where('full_slug', $fullSlug)->first();
        if ($existing) {
            return $existing;
        }

        return MarketplaceCategory::create([
            'parent_id'                 => $parent?->id,
            'name'                      => $name,
            'slug'                      => $slug,
            'icon'                      => $icon,
            'description'               => $desc,
            'sort_order'                => $sort,
            'is_active'                 => true,
            'is_visible_in_marketplace' => true,
            'is_leaf'                   => true,        // será recalculado por hooks al crear hijos
            'allow_seller_publish'      => true,
        ]);
    }
}
