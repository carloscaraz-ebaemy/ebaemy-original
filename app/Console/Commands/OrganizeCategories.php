<?php

namespace App\Console\Commands;

use Hyn\Tenancy\Environment;
use Hyn\Tenancy\Models\Website;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Item\Models\Category;

/**
 * Agrupa las categorías sueltas de un tenant bajo un puñado de padres.
 *
 * No inventa la clasificación de cero: propone, enseña la propuesta y sólo
 * escribe con `--apply`. La última palabra la tiene el comerciante desde el
 * panel, que es quien conoce su catálogo.
 */
class OrganizeCategories extends Command
{
    protected $signature = 'categories:organize
                            {tenant? : uuid del tenant (ej. ebaemy_carolayimport). Sin él, los recorre todos}
                            {--apply : escribe los cambios. Sin este flag sólo enseña la propuesta}
                            {--keep-names : no normaliza los nombres, sólo agrupa}
                            {--min=12 : no toca tiendas con menos categorías que esto}';

    protected $description = 'Agrupa las categorías planas del tenant en padres (Electrónica, Hogar...) y normaliza sus nombres';

    /**
     * Diccionario de agrupación.
     *
     * El orden IMPORTA: gana la primera coincidencia, así que lo específico va
     * antes que lo general («powerbank» antes que «electr»). Las claves se
     * comparan contra el nombre en minúsculas y sin tildes.
     */
    private const RULES = [
        'Relojes y Accesorios' => [
            'reloj', 'cronometro', 'correa de reloj',
        ],
        'Computación' => [
            'computador', 'laptop', 'notebook', 'teclado', 'mouse', 'mousepad',
            'impresora', 'monitor', 'usb', 'disco duro', 'router',
        ],
        'Celulares y Accesorios' => [
            'telefono movil', 'celular', 'powerbank', 'power bank', 'cargador',
            'soporte de tel', 'funda', 'cable usb', 'transmisor fm', 'transmisores fm',
        ],
        'Fotografía y Video' => [
            'camara', 'fotograf', 'flash', 'tripode', 'lente', 'iluminacion de estudio',
        ],
        'Electrónica y Audio' => [
            'parlante', 'audio', 'audifono', 'microfono', 'megafono', 'karaoke',
            'radio', 'televis', 'electronic', 'bluetooth', 'grabadora',
        ],
        'Bebés y Niños' => [
            'bebe', 'niño', 'nino', 'infantil', 'pañal', 'panal', 'mamadera',
            'coche de bebe', 'chupete', 'biberon', 'aspirador nasal', 'urinal',
            'cuna', 'moises', 'canasto portatil',
        ],
        'Juguetes y Juegos' => [
            'juguete', 'muñeca', 'muneca', 'marioneta', 'peluche', 'rompecabeza',
            'juego de tablero', 'juegos de tablero', 'mesas de juegos', 'juegos al aire libre',
            'estructuras de juegos',
        ],
        'Belleza y Cuidado Personal' => [
            'cabello', 'champu', 'shampoo', 'cosmetic', 'maquillaje', 'brocha',
            'afeitad', 'cuchilla', 'depilac', 'depilar', 'vello', 'uñas', 'unas',
            'perfume', 'crema', 'cuidado personal', 'secador', 'cuidado de la piel',
            'desmaquillad',
            'exfoliante', 'mascaras', 'pestaña', 'pestana', 'after-sun', 'hidratante',
            'higiene personal', 'higiene bucal', 'cuidado bucal', 'aclarador de piel',
            'pañuelos faciales', 'cuidado de oidos', 'cuidado de los pies',
        ],
        'Salud y Bienestar' => [
            'masaje', 'rehabilitac', 'medic', 'ortoped', 'tensiometro', 'nebulizador',
            'tonificacion', 'tonificar', 'balanza de baño', 'balanza corporal',
            'vitamina', 'mineral',
            'aliviar el sueño', 'aliviar el sueno', 'alivio del dolor', 'estres',
            'energetico', 'baston', 'talonera', 'termometro',
        ],
        'Deportes y Aire Libre' => [
            'gimnasio', 'gimnasia', 'deportiv', 'bicicleta', 'mancuerna', 'pesas',
            'binocular', 'camping', 'aire libre', 'voley', 'volley', 'basquet',
            'futbol', 'natacion', 'entrenamiento', 'fitness',
        ],
        'Cocina y Limpieza' => [
            'cocina', 'utensilio', 'aspiradora', 'limpiador', 'limpieza', 'detergente',
            'olla', 'sarten', 'licuadora', 'cafeter', 'horno', 'filtro de agua', 'balanza',
            'dispensador', 'tendedero', 'plancha', 'alimento', 'exprimidor',
            'molinillo', 'molino', 'salero', 'pimentero', 'vasos', 'copas', 'parrilla',
            'asador', 'pelador', 'recipiente', 'sandwich', 'sándwich', 'waffle',
            'pastel', 'postre', 'papelera', 'basura', 'descalcificador', 'bebidas',
        ],
        'Hogar y Decoración' => [
            'hogar', 'decorac', 'adorno', 'lampara', 'luz', 'foco', 'espejo', 'almohada',
            'organizador', 'dormitorio', 'living', 'mueble', 'cortina', 'alfombra',
            'marco para foto', 'marcos para foto', 'ventilador', 'calefact', 'calentador', 'calefon',
            'estufa', 'navidad', 'difusor', 'joyero', 'caja de seguridad', 'cesta',
            'mesa', 'silla', 'taburete', 'cajon', 'comoda', 'escritorio', 'cojin',
            'paraguas', 'mosquitera', 'humidificador', 'vaporizador', 'jardin',
            'electrodomestico', 'estante', 'expositor',
        ],
        'Moda y Accesorios' => [
            'ropa', 'camison', 'camisola', 'prenda', 'zapato', 'calzado', 'cartera',
            'mochila', 'bolso', 'lentes de sol', 'joyer', 'bisuter', 'accesorio personal',
            'chaqueta', 'blazer', 'cardigan', 'chaleco', 'gorra', 'correa', 'arnes',
            'equipaje', 'maleta', 'cinturon',
        ],
        'Mascotas' => [
            'mascota', 'perro', 'gato',
        ],
        'Herramientas y Ferretería' => [
            'herramienta', 'taladro', 'compresor', 'conector', 'cable de extension',
            'alargador', 'tornillo', 'ferreter', 'hidrolavadora', 'cable', 'velcro',
            'boton', 'gancho', 'perno', 'cierre', 'pincel', 'pintura', 'tubo flexible',
        ],
        'Seguridad' => [
            'seguridad', 'vigilancia', 'autodefensa', 'gafas protectoras', 'proteccion',
        ],
        'Oficina y Papelería' => [
            'oficina', 'papeleria', 'tablero de dibujo', 'cuaderno', 'archivador',
        ],
    ];

    public function handle(): int
    {
        $uuid = $this->argument('tenant');

        $websites = $uuid
            ? Website::where('uuid', $uuid)->get()
            : Website::all();

        if ($websites->isEmpty()) {
            $this->error('No encontré ese tenant.');
            return self::FAILURE;
        }

        foreach ($websites as $website) {
            $this->processTenant($website);
        }

        if (!$this->option('apply')) {
            $this->newLine();
            $this->warn('Esto fue una SIMULACIÓN. Nada se escribió. Añade --apply para aplicarlo.');
        }

        return self::SUCCESS;
    }

    private function processTenant(Website $website): void
    {
        app(Environment::class)->tenant($website);

        $total = Category::count();
        $min   = (int) $this->option('min');

        if ($total < $min) {
            $this->line("<fg=gray>— {$website->uuid}: {$total} categorías, no hace falta agrupar.</>");
            return;
        }

        $this->newLine();
        $this->info("═══ {$website->uuid} — {$total} categorías");

        $counts = DB::connection('tenant')->table('items')
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) AS total')
            ->groupBy('category_id')
            ->pluck('total', 'category_id');

        // Sólo se reagrupa lo que hoy cuelga de la raíz. Si el comerciante ya
        // movió cosas a mano, no se le deshace el trabajo.
        $loose = Category::whereNull('parent_id')
            ->whereNotIn('id', Category::whereNotNull('parent_id')->pluck('parent_id')->filter()->all())
            ->get();

        $plan = [];

        foreach ($loose as $category) {
            $name   = $this->option('keep-names')
                ? $category->name
                : Category::normalizeName($category->name);
            $parent = $this->guessParent($name) ?? Category::UNCLASSIFIED;

            $plan[$parent][] = [
                'category' => $category,
                'new_name' => $name,
                'items'    => (int) ($counts[$category->id] ?? 0),
            ];
        }

        // Los padres se ordenan por número de productos: el grupo con más
        // catálogo detrás es el que el comprador espera encontrar primero.
        uasort($plan, function ($a, $b) {
            return array_sum(array_column($b, 'items')) <=> array_sum(array_column($a, 'items'));
        });

        foreach ($plan as $parentName => $rows) {
            $items = array_sum(array_column($rows, 'items'));
            $this->line(sprintf(
                '  <options=bold>%s</> <fg=gray>(%d subcategorías · %d productos)</>',
                $parentName,
                count($rows),
                $items
            ));

            usort($rows, fn ($a, $b) => strcmp($a['new_name'], $b['new_name']));

            foreach ($rows as $row) {
                $renamed = $row['new_name'] !== $row['category']->name
                    ? " <fg=yellow>← {$row['category']->name}</>"
                    : '';
                $this->line(sprintf('      %s %s%s', str_pad((string) $row['items'], 4), $row['new_name'], $renamed));
            }
        }

        if (!$this->option('apply')) {
            return;
        }

        $this->apply($plan);
        $this->info("  ✓ Aplicado: " . count($plan) . " categorías padre.");
    }

    /**
     * Escribe la propuesta. Todo dentro de una transacción: o queda el árbol
     * entero o no queda nada, porque un catálogo a medio agrupar se ve peor
     * que uno plano.
     */
    private function apply(array $plan): void
    {
        DB::connection('tenant')->transaction(function () use ($plan) {
            $order = 1;

            foreach ($plan as $parentName => $rows) {
                $parent = Category::whereNull('parent_id')
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($parentName)])
                    ->first();

                if (!$parent) {
                    $parent = Category::create([
                        'name'              => $parentName,
                        'image'             => 'imagen-no-disponible.jpg',
                        'visible_ecommerce' => $parentName !== Category::UNCLASSIFIED,
                    ]);
                }

                $parent->forceFill([
                    'sort_order' => $order++,
                    'parent_id'  => null,
                ])->save();

                foreach ($rows as $row) {
                    /** @var Category $category */
                    $category = $row['category'];

                    if ($category->id === $parent->id) {
                        continue;
                    }

                    $category->forceFill([
                        'parent_id'  => $parent->id,
                        'name'       => $row['new_name'],
                        'sort_order' => 0, // dentro del grupo, alfabético
                    ])->save();
                }
            }
        });
    }

    /**
     * Devuelve el padre que le toca a un nombre, o null si ninguna regla casa.
     */
    private function guessParent(string $name): ?string
    {
        $haystack = $this->fold($name);

        $words = preg_split('/[^a-z0-9ñ]+/u', $haystack, -1, PREG_SPLIT_NO_EMPTY);

        foreach (self::RULES as $parent => $keywords) {
            foreach ($keywords as $keyword) {
                if ($this->matches($words, $this->fold($keyword))) {
                    return $parent;
                }
            }
        }

        return null;
    }

    /**
     * ¿Aparece la clave en el nombre?
     *
     * No basta un `str_contains`: los nombres reales vienen en plural
     * («Soportes de teléfono», «Conectores eléctricos», «Aclaradores de piel»)
     * y la clave en singular deja de ser subcadena por una «s». Se compara
     * palabra a palabra aceptando que la del nombre EMPIECE por la de la
     * clave, que es lo que hace el plural, y exigiendo que vayan seguidas para
     * que «cable» y «usb» sueltos no valgan por «cable usb».
     */
    private function matches(array $words, string $keyword): bool
    {
        $needle = preg_split('/[^a-z0-9ñ]+/u', $keyword, -1, PREG_SPLIT_NO_EMPTY);

        if (!$needle) {
            return false;
        }

        $limit = count($words) - count($needle);

        for ($i = 0; $i <= $limit; $i++) {
            $hit = true;

            foreach ($needle as $k => $token) {
                if (strncmp($words[$i + $k], $token, strlen($token)) !== 0) {
                    $hit = false;
                    break;
                }
            }

            if ($hit) {
                return true;
            }
        }

        return false;
    }

    /** Minúsculas y sin tildes, para que «Cámara» case con «camara». */
    private function fold(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        return strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);
    }
}
