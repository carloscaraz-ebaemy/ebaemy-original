<?php

namespace App\Services\Tenant;

use App\Models\Tenant\Catalogs\Department;
use App\Models\Tenant\Catalogs\District;
use App\Models\Tenant\Catalogs\Province;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Busqueda de destino sobre los TRES niveles del ubigeo.
 *
 * El buscador anterior consultaba unicamente `districts`. Por eso "Talara"
 * devolvia cero: Talara es una PROVINCIA (2007, Piura), no un distrito; sus
 * distritos se llaman Parinas, El Alto, La Brea, Lobitos, Los Organos y
 * Mancora. El cliente escribe el nombre que conoce -la ciudad- y el sistema
 * solo sabia mirar el nivel mas profundo.
 *
 * Ademas ordenaba alfabeticamente y cortaba en 25, asi que "hua" devolvia
 * Ahuac, Ahuaycha, Ancahuasi... y nunca Huancayo. Para el usuario eso es
 * indistinguible de "no existe".
 *
 * Aqui se busca en departamentos, provincias y distritos a la vez, por
 * TOKENS (asi "madre dios" encuentra Madre de Dios y los espacios dobles
 * dejan de importar) y se ordena por PUNTAJE, no por alfabeto.
 *
 * Tildes y mayusculas NO necesitan tratamiento en el SQL: la colacion de las
 * tres tablas es utf8mb4_unicode_ci, que ya ignora ambas ("parinas" encuentra
 * PARINAS). La normalizacion de aqui es solo para puntuar en PHP.
 *
 * Toda fila de tipo distrito resuelve a un destino real, porque el envio
 * necesita un `district_id`: una coincidencia de provincia arrastra a sus
 * distritos en vez de quedar en un callejon sin salida.
 */
class UbigeoSearch
{
    /** Menos de esto son demasiadas coincidencias para ser utiles. */
    public const MIN_LENGTH = 2;

    public const DEFAULT_LIMIT = 12;

    /**
     * Una provincia vuelca sus distritos en el resultado si son pocos
     * (Talara: 6). Lima tiene 43 y el departamento 171: esos se exploran
     * pulsando el resultado, no volcandolos en la lista.
     */
    private const INLINE_EXPAND_MAX = 14;

    private const CACHE_TTL = 86400;

    /**
     * @param  bool  $withGroups  true = incluye filas de provincia y
     *                            departamento (las agrupa la UI nueva).
     *                            false = solo distritos, que es lo unico que
     *                            el widget actual sabe seleccionar.
     * @return array<int, array<string, mixed>>
     */
    public static function search(string $raw, int $limit = self::DEFAULT_LIMIT, bool $withGroups = false): array
    {
        return self::searchIn(self::catalog(), $raw, $limit, $withGroups);
    }

    /**
     * La busqueda sobre un catalogo ya cargado. Separada de `search()` para
     * que el ranking se pueda probar con un catalogo de mentira, sin base de
     * datos: es logica con seis criterios y se degrada sin que nadie lo note.
     *
     * Ojo con una consecuencia de buscar en PHP y no en SQL: la colacion
     * accent-insensitive de MySQL ya no interviene. Que "parinas" encuentre
     * PARIÑAS depende ahora de normalize(), no de la base.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function searchIn(array $catalog, string $raw, int $limit = self::DEFAULT_LIMIT, bool $withGroups = false): array
    {
        $query = self::normalize($raw);

        if (mb_strlen($query) < self::MIN_LENGTH) {
            return [];
        }

        $tokens = array_values(array_filter(explode(' ', $query), fn ($t) => $t !== ''));

        if (!$tokens) {
            return [];
        }

        $hits    = [];   // district_id => fila
        $groups  = [];   // filas de provincia / departamento

        // -- Nivel 3: distritos que coinciden directamente ----------------
        foreach ($catalog['districts'] as $d) {
            $base = self::score($d['norm'], $query, $tokens);
            if ($base === null) {
                continue;
            }
            self::keepBest($hits, self::districtRow($d, $base + self::districtBonus($d)));
        }

        // -- Nivel 2: provincias. La fila de provincia no basta: lo que el
        //    envio necesita es un distrito. "Talara" tiene que terminar
        //    ofreciendo Parinas, no un callejon sin salida.
        foreach ($catalog['provinces'] as $p) {
            $base = self::score($p['norm'], $query, $tokens);
            if ($base === null) {
                continue;
            }

            $children = $catalog['byProvince'][$p['id']] ?? [];
            $groups[] = self::provinceRow($p, $catalog, $base + 15, count($children));

            if (count($children) > self::INLINE_EXPAND_MAX) {
                continue;
            }

            foreach ($children as $d) {
                // Hereda el puntaje de su provincia, por debajo de una
                // coincidencia directa; la capital homonima sube.
                $inherited = $base - 20 + self::districtBonus($d)
                    + ($d['norm'] === $p['norm'] ? 18 : 0);
                self::keepBest($hits, self::districtRow($d, $inherited, $p['name']));
            }
        }

        // -- Nivel 1: departamentos. Nunca se vuelcan: Lima son 171
        //    distritos. Se ofrecen como punto de partida para explorar.
        foreach ($catalog['departments'] as $dep) {
            $base = self::score($dep['norm'], $query, $tokens);
            if ($base === null) {
                continue;
            }
            $groups[] = self::departmentRow($dep, $catalog, $base + 10);
        }

        $rows = $withGroups
            ? array_merge($groups, array_values($hits))
            : array_values($hits);

        usort($rows, function ($a, $b) {
            return ($b['score'] <=> $a['score']) ?: strcmp($a['name'], $b['name']);
        });

        return array_slice($rows, 0, max(1, $limit));
    }

    // -----------------------------------------------------------------
    // Puntaje
    // -----------------------------------------------------------------

    /**
     * Devuelve null si el candidato no coincide. Coincide cuando TODOS los
     * tokens aparecen en el nombre: asi "madre dios" alcanza a "Madre de
     * Dios", que un LIKE '%madre dios%' nunca encontraria.
     */
    private static function score(string $norm, string $query, array $tokens): ?float
    {
        foreach ($tokens as $t) {
            if (!str_contains($norm, $t)) {
                return null;
            }
        }

        if ($norm === $query) {
            return 100;
        }

        if (str_starts_with($norm, $query)) {
            return 60;
        }

        // Cada token arranca una palabra del nombre ("san martin" ->
        // "San Martin de Porres"). Vale mas que caer en medio de otra
        // palabra ("tala" dentro de "Matalaque").
        $words = explode(' ', $norm);
        foreach ($tokens as $t) {
            $ok = false;
            foreach ($words as $w) {
                if (str_starts_with($w, $t)) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                return 10;
            }
        }

        return 35;
    }

    /** Nombre corto = mas probable que sea el que buscaban. */
    private static function districtBonus(array $d): float
    {
        $bonus = -0.2 * mb_strlen($d['norm']);

        // Capital homonima de su provincia (Huancayo, Cusco, Arequipa):
        // es casi siempre el destino que la gente quiere decir.
        if ($d['norm'] === $d['province_norm']) {
            $bonus += 25;
        }

        // En el UBIGEO del INEI el distrito capital de la provincia lleva
        // sufijo 01. Sirve justo donde el nombre no ayuda: quien escribe
        // "Talara" casi siempre quiere Parinas (200701), que no se llama
        // como su provincia y sin esto quedaba ultimo por orden alfabetico.
        if (substr($d['id'], 4) === '01') {
            $bonus += 12;
        }

        // Lima y Callao concentran el grueso de los envios reales.
        if ($d['department_id'] === '15' || $d['department_id'] === '07') {
            $bonus += 8;
        }

        return $bonus;
    }

    private static function keepBest(array &$hits, array $row): void
    {
        $id = $row['district_id'];
        if (!isset($hits[$id]) || $hits[$id]['score'] < $row['score']) {
            $hits[$id] = $row;
        }
    }

    // -----------------------------------------------------------------
    // Filas
    // -----------------------------------------------------------------

    private static function districtRow(array $d, float $score, ?string $viaProvince = null): array
    {
        return [
            'type'          => 'district',
            'district_id'   => $d['id'],
            'province_id'   => $d['province_id'],
            'department_id' => $d['department_id'],
            'name'          => $d['name'],
            // Nombres sueltos ademas del `context` ya armado: la UI necesita
            // rotular la vista de distritos al abrir una provincia, y sacarlos
            // partiendo la cadena por el punto medio seria pedir un bug.
            'province_name'   => $d['province_name'],
            'department_name' => $d['department_name'],
            'context'       => 'Distrito · ' . $d['province_name'] . ' · ' . $d['department_name'],
            // `label` es el contrato que ya consume el widget actual.
            'label'         => $d['name'] . ' — ' . $d['province_name'] . ', ' . $d['department_name'],
            'group'         => $d['province_id'],
            'via'           => $viaProvince,
            'score'         => round($score, 2),
        ];
    }

    private static function provinceRow(array $p, array $catalog, float $score, int $count): array
    {
        $depName = $catalog['departments'][$p['department_id']]['name'] ?? '';

        // Si la provincia tiene un distrito con su mismo nombre (Huancayo,
        // Cusco), elegirla puede resolverse sola. Talara no lo tiene: ahi la
        // fila solo sirve para abrir sus distritos.
        $capital = null;
        foreach ($catalog['byProvince'][$p['id']] ?? [] as $d) {
            if ($d['norm'] === $p['norm']) {
                $capital = $d['id'];
                break;
            }
        }

        return [
            'type'           => 'province',
            'district_id'    => $capital,
            'province_id'    => $p['id'],
            'department_id'  => $p['department_id'],
            'name'            => $p['name'],
            'province_name'   => $p['name'],
            'department_name' => $depName,
            'context'        => 'Provincia · ' . $depName,
            'label'          => $p['name'] . ' (provincia) — ' . $depName,
            'group'          => $p['id'],
            'district_count' => $count,
            'score'          => round($score, 2),
        ];
    }

    private static function departmentRow(array $dep, array $catalog, float $score): array
    {
        $count = 0;
        foreach ($catalog['provinces'] as $p) {
            if ($p['department_id'] === $dep['id']) {
                $count++;
            }
        }

        return [
            'type'           => 'department',
            'district_id'    => null,
            'province_id'    => null,
            'department_id'  => $dep['id'],
            'name'            => $dep['name'],
            'province_name'   => null,
            'department_name' => $dep['name'],
            'context'        => 'Departamento',
            'label'          => $dep['name'] . ' (departamento)',
            'group'          => $dep['id'],
            'province_count' => $count,
            'score'          => round($score, 2),
        ];
    }

    // -----------------------------------------------------------------
    // Catalogo
    // -----------------------------------------------------------------

    /**
     * 25 + 196 + 1.875 filas: 67 KB. Cabe de sobra en memoria y evita tres
     * consultas por pulsacion de tecla.
     *
     * OJO: el catalogo vive en la base de CADA tenant (UsesTenantConnection)
     * y ya diverge entre ellas (un tenant tiene 1.876 distritos). La clave de
     * cache lleva el nombre de la base o un tenant veria el ubigeo de otro.
     */
    public static function catalog(): array
    {
        return Cache::remember(self::cacheKey(), self::CACHE_TTL, function () {
            return self::buildCatalog(
                Department::orderBy('description')->get(['id', 'description']),
                Province::orderBy('description')->get(['id', 'description', 'department_id']),
                District::orderBy('description')->get(['id', 'description', 'province_id'])
            );
        });
    }

    /**
     * Indexa las tres listas. Acepta cualquier iterable de objetos con `id`,
     * `description` y el padre: asi el test puede alimentarlo sin base de
     * datos y seguir ejerciendo el normalizado y el `pretty()` de verdad.
     *
     * @return array{departments: array, provinces: array, districts: array, byProvince: array}
     */
    public static function buildCatalog(iterable $deps, iterable $provs, iterable $dists): array
    {
        $departments = [];
        foreach ($deps as $d) {
            $departments[$d->id] = [
                'id'   => $d->id,
                'name' => self::pretty($d->description),
                'norm' => self::normalize($d->description),
            ];
        }

        $provinces = [];
        foreach ($provs as $p) {
            $provinces[$p->id] = [
                'id'            => $p->id,
                'name'          => self::pretty($p->description),
                'norm'          => self::normalize($p->description),
                'department_id' => $p->department_id,
            ];
        }

        $districts  = [];
        $byProvince = [];
        foreach ($dists as $d) {
            $prov = $provinces[$d->province_id] ?? null;
            $dep  = $prov ? ($departments[$prov['department_id']] ?? null) : null;

            $row = [
                'id'              => $d->id,
                'name'            => self::pretty($d->description),
                'norm'            => self::normalize($d->description),
                'province_id'     => $d->province_id,
                'province_name'   => $prov ? $prov['name'] : '',
                'province_norm'   => $prov ? $prov['norm'] : '',
                'department_id'   => $dep ? $dep['id'] : null,
                'department_name' => $dep ? $dep['name'] : '',
            ];

            $districts[] = $row;
            $byProvince[$d->province_id][] = $row;
        }

        return compact('departments', 'provinces', 'districts', 'byProvince');
    }

    /** Se invalida si algun dia cambia el catalogo de un tenant. */
    public static function forget(): void
    {
        Cache::forget(self::cacheKey());
    }

    private static function cacheKey(): string
    {
        $db = DB::connection((new District)->getConnectionName())->getDatabaseName();

        return 'ubigeo.catalog.' . $db;
    }

    // -----------------------------------------------------------------
    // Texto
    // -----------------------------------------------------------------

    /** minusculas, sin tildes, sin espacios de mas. Solo para puntuar. */
    public static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, [
            'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
            'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
            'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
            'ñ' => 'n', 'ç' => 'c',
        ]);
        // El catalogo trae "Anco_Huallo" con guion bajo: para buscar es un
        // separador mas, no parte del nombre.
        $s = str_replace(['_', '-', '.', ','], ' ', $s);

        return trim(preg_replace('/\s+/u', ' ', $s));
    }

    /**
     * El catalogo mezcla "PARINAS", "Los Organos" y "San Martin de Porres".
     * Los resultados se leen mejor con una sola forma; el dato guardado
     * (el UBIGEO) no cambia.
     */
    private static function pretty(string $s): string
    {
        // "Anco_Huallo" es como esta en la tabla. El guion bajo es un
        // artefacto del volcado del catalogo, no parte del nombre que el
        // cliente tiene que leer. El UBIGEO guardado no cambia.
        $s = str_replace('_', ' ', trim($s));

        if ($s !== mb_strtoupper($s, 'UTF-8')) {
            return $s;   // ya viene mezclado: respetar lo que hay
        }

        $menores  = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'en'];
        $palabras = explode(' ', mb_strtolower($s, 'UTF-8'));

        foreach ($palabras as $i => $w) {
            $palabras[$i] = ($i > 0 && in_array($w, $menores, true))
                ? $w
                : mb_convert_case($w, MB_CASE_TITLE, 'UTF-8');
        }

        return implode(' ', $palabras);
    }
}
