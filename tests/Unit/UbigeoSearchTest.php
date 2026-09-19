<?php

namespace Tests\Unit;

use App\Services\Tenant\UbigeoSearch;
use PHPUnit\Framework\TestCase;

/**
 * El caso que originó esto: el cliente escribía «Talara» y el buscador decía
 * «Sin resultados». Talara es una PROVINCIA de Piura (2007), no un distrito, y
 * la consulta miraba sólo la tabla `districts`.
 *
 * Detrás había un segundo fallo más silencioso: `ORDER BY description LIMIT 25`
 * hacía que «hua» devolviera Ahuac, Ahuaycha, Ancahuasi… y nunca Huancayo. Para
 * el usuario eso es indistinguible de «no existe», y es justo el tipo de cosa
 * que se degrada sin que nadie lo note: por eso estas pruebas fijan el ORDEN y
 * no sólo la presencia.
 *
 * Se prueba con `searchIn()` sobre un catálogo armado a mano —los mismos
 * UBIGEO y la misma escritura irregular que tiene el catálogo real— para que
 * no haga falta base de datos. La escritura importa: `PARIÑAS` está en
 * mayúsculas con tilde, `Los Organos` en minúsculas SIN tilde y `Anco_Huallo`
 * lleva guion bajo. Esa mezcla es real.
 */
class UbigeoSearchTest extends TestCase
{
    /**
     * Ojo: al buscar en PHP y no en SQL, la colación accent-insensitive de
     * MySQL ya no interviene. Que «parinas» encuentre PARIÑAS depende de
     * normalize(), y por eso se prueba aquí.
     */
    private function catalogo(): array
    {
        $dep = function ($id, $desc) { return (object) ['id' => $id, 'description' => $desc]; };
        $prov = function ($id, $desc, $depId) { return (object) ['id' => $id, 'description' => $desc, 'department_id' => $depId]; };
        $dist = function ($id, $desc, $provId) { return (object) ['id' => $id, 'description' => $desc, 'province_id' => $provId]; };

        return UbigeoSearch::buildCatalog(
            [
                $dep('20', 'PIURA'),
                $dep('12', 'JUNÍN'),
                $dep('15', 'LIMA'),
                $dep('07', 'CALLAO'),
                $dep('17', 'MADRE DE DIOS'),
                $dep('22', 'SAN MARTIN'),
                $dep('04', 'AREQUIPA'),
                $dep('03', 'APURIMAC'),
            ],
            [
                $prov('2007', 'Talara', '20'),
                $prov('2001', 'Piura', '20'),
                $prov('1201', 'Huancayo', '12'),
                $prov('1206', 'Chupaca', '12'),
                $prov('1501', 'Lima', '15'),
                $prov('0701', 'Prov. Const. del Callao', '07'),
                $prov('1702', 'Manu', '17'),
                $prov('2203', 'San Martín', '22'),
                $prov('0401', 'Arequipa', '04'),
                $prov('0302', 'Chincheros', '03'),
            ],
            [
                // Talara: ninguno se llama como su provincia. Pariñas es la
                // capital y se reconoce sólo por el sufijo 01 del UBIGEO.
                $dist('200701', 'PARIÑAS', '2007'),
                $dist('200702', 'EL ALTO', '2007'),
                $dist('200703', 'LA BREA', '2007'),
                $dist('200704', 'LOBITOS', '2007'),
                $dist('200705', 'Los Organos', '2007'),
                $dist('200706', 'MANCORA', '2007'),

                $dist('200101', 'Piura', '2001'),
                $dist('200104', 'Castilla', '2001'),

                $dist('120101', 'Huancayo', '1201'),
                $dist('120104', 'Chilca', '1201'),
                // Los que sepultaban a Huancayo en el orden alfabético. Cada
                // uno bajo su provincia de verdad: Ahuac es de Chupaca y
                // Anco_Huallo de Chincheros, no de Huancayo.
                $dist('120602', 'Ahuac', '1206'),
                $dist('030201', 'Anco_Huallo', '0302'),

                $dist('150101', 'Lima', '1501'),
                $dist('150135', 'San Martín de Porres', '1501'),
                // «Santa Rosa» está 10 veces en el catálogo real. Dos bastan
                // para exigir que el contexto permita distinguirlas.
                $dist('150139', 'Santa Rosa', '1501'),
                $dist('220301', 'Santa Rosa', '2203'),

                $dist('070107', 'Mi Perú', '0701'),
                $dist('170203', 'Madre de Dios', '1702'),
                $dist('220303', 'San Martín', '2203'),
                $dist('040101', 'Arequipa', '0401'),
            ]
        );
    }

    private function buscar(string $q, bool $conGrupos = false, int $limite = 12): array
    {
        return UbigeoSearch::searchIn($this->catalogo(), $q, $limite, $conGrupos);
    }

    /** @return string[] */
    private function nombres(string $q, bool $conGrupos = false): array
    {
        return array_map(fn ($r) => $r['name'], $this->buscar($q, $conGrupos));
    }

    // ── El caso que originó todo ─────────────────────────────────────────

    public function test_talara_encuentra_su_provincia_y_sus_distritos(): void
    {
        $rows = $this->buscar('Talara', true);

        $this->assertNotEmpty($rows, 'Talara no puede volver a dar "Sin resultados"');

        $this->assertSame('province', $rows[0]['type'], 'la provincia Talara debe encabezar');
        $this->assertSame('Talara', $rows[0]['name']);
        $this->assertSame('2007', $rows[0]['province_id']);
        $this->assertSame(6, $rows[0]['district_count']);

        // Pariñas es la capital y NO se llama como su provincia: sin el bonus
        // del sufijo 01 quedaba última por orden alfabético, que es justo el
        // distrito que el cliente quería.
        $this->assertSame('Pariñas', $rows[1]['name'], 'la capital de Talara debe ir primero');
        $this->assertSame('200701', $rows[1]['district_id']);

        // Los seis distritos de la provincia tienen que estar disponibles.
        $ids = array_column($rows, 'district_id');
        foreach (['200701', '200702', '200703', '200704', '200705', '200706'] as $id) {
            $this->assertContains($id, $ids, "falta el distrito $id de Talara");
        }
    }

    /** @dataProvider variantesDeTalara */
    public function test_talara_es_indiferente_a_mayusculas(string $q): void
    {
        $this->assertSame(
            ['Talara', 'Pariñas'],
            array_slice($this->nombres($q, true), 0, 2),
            "«{$q}» debe dar el mismo resultado"
        );
    }

    public function variantesDeTalara(): array
    {
        return [['Talara'], ['talara'], ['TALARA'], ['  talara  '], ['TaLaRa']];
    }

    public function test_talara_parcial_no_se_pierde_entre_matalaque_y_talavera(): void
    {
        // Con el buscador viejo, «tala» devolvía Matalaque y Talavera pero no
        // Talara: la coincidencia al principio de palabra tiene que ganarle a
        // la que cae en medio de otra.
        $this->assertSame('Talara', $this->nombres('tala', true)[0]);
    }

    public function test_una_provincia_sin_distrito_homonimo_no_es_seleccionable(): void
    {
        // El envío necesita un district_id. Talara no tiene distrito homónimo,
        // así que su fila sirve para abrir sus distritos, no para elegirla.
        $prov = $this->buscar('Talara', true)[0];
        $this->assertNull($prov['district_id']);

        // Huancayo sí lo tiene: ahí la fila puede resolverse sola.
        $huancayo = null;
        foreach ($this->buscar('Huancayo', true) as $r) {
            if ($r['type'] === 'province') { $huancayo = $r; break; }
        }
        $this->assertNotNull($huancayo);
        $this->assertSame('120101', $huancayo['district_id']);
    }

    // ── Ranking: lo que el LIMIT alfabético escondía ─────────────────────

    public function test_hua_devuelve_huancayo_y_no_ahuac(): void
    {
        $nombres = $this->nombres('hua');

        $this->assertSame('Huancayo', $nombres[0], 'Huancayo debe encabezar «hua»');
        // «Ahuac» coincide en medio de la palabra: puede aparecer, pero nunca
        // por delante de una coincidencia al principio.
        $this->assertLessThan(
            array_search('Ahuac', $nombres, true) ?: PHP_INT_MAX,
            array_search('Huancayo', $nombres, true)
        );
    }

    public function test_la_capital_homonima_encabeza_su_busqueda(): void
    {
        foreach (['lima' => 'Lima', 'arequipa' => 'Arequipa', 'piura' => 'Piura'] as $q => $esperado) {
            $this->assertSame($esperado, $this->nombres($q)[0], "«{$q}» debe encabezar con $esperado");
        }
    }

    public function test_la_coincidencia_exacta_gana_a_la_que_solo_contiene(): void
    {
        // «San Martín» exacto por delante de «San Martín de Porres».
        $nombres = $this->nombres('san martin');
        $this->assertSame('San Martín', $nombres[0]);
        $this->assertContains('San Martín de Porres', $nombres);
    }

    // ── Tildes, espacios y varias palabras ───────────────────────────────

    /** @dataProvider paresConYSinTilde */
    public function test_las_tildes_no_cambian_el_resultado(string $sinTilde, string $conTilde): void
    {
        // Con grupos, porque algunos de estos nombres sólo existen como
        // departamento («Junín») y sin las filas de grupo darían vacío.
        $this->assertSame(
            $this->nombres($conTilde, true),
            $this->nombres($sinTilde, true),
            "«{$sinTilde}» y «{$conTilde}» deben dar lo mismo"
        );
        $this->assertNotEmpty($this->nombres($sinTilde, true), "«{$sinTilde}» no puede dar vacío");
    }

    public function paresConYSinTilde(): array
    {
        return [
            ['parinas', 'Pariñas'],
            ['mancora', 'Máncora'],
            ['los organos', 'Los Órganos'],
            ['mi peru', 'Mi Perú'],
            ['san martin', 'San Martín'],
            ['junin', 'Junín'],
        ];
    }

    public function test_los_espacios_de_mas_no_rompen_la_busqueda(): void
    {
        $esperado = $this->nombres('san martin');

        foreach (['san  martin', '  san martin', "san\tmartin", 'san   martin  '] as $q) {
            $this->assertSame($esperado, $this->nombres($q), "«{$q}» debe dar lo mismo");
        }
    }

    public function test_busca_por_palabras_sueltas_y_no_por_substring_contiguo(): void
    {
        // Un LIKE '%madre dios%' nunca encuentra «Madre de Dios». Éste sí.
        $nombres = $this->nombres('madre dios', true);
        $this->assertNotEmpty($nombres);
        $this->assertContains('Madre de Dios', $nombres);
    }

    public function test_el_guion_bajo_del_catalogo_se_trata_como_separador(): void
    {
        // En el catálogo real el distrito se llama «Anco_Huallo».
        $this->assertContains('Anco Huallo', $this->nombres('anco huallo'));
        $this->assertContains('Anco Huallo', $this->nombres('huallo'));
    }

    // ── Nombres repetidos ────────────────────────────────────────────────

    public function test_los_nombres_repetidos_se_distinguen_por_su_contexto(): void
    {
        $rows = array_values(array_filter(
            $this->buscar('santa rosa'),
            fn ($r) => $r['name'] === 'Santa Rosa'
        ));

        $this->assertCount(2, $rows);
        $this->assertNotSame(
            $rows[0]['context'],
            $rows[1]['context'],
            'dos "Santa Rosa" con el mismo texto son imposibles de distinguir'
        );
        $this->assertNotSame($rows[0]['district_id'], $rows[1]['district_id']);
    }

    // ── Contrato de la respuesta ─────────────────────────────────────────

    public function test_sin_grupos_todo_resultado_es_un_destino_elegible(): void
    {
        // El widget que está en producción sólo sabe seleccionar distritos:
        // si aquí se colara una provincia, el campo se vaciaría al elegirla.
        foreach (['talara', 'lima', 'piura', 'hua', 'san martin'] as $q) {
            foreach ($this->buscar($q) as $r) {
                $this->assertSame('district', $r['type'], "«{$q}» devolvió un {$r['type']}");
                $this->assertNotEmpty($r['district_id']);
                $this->assertNotEmpty($r['province_id']);
                $this->assertNotEmpty($r['department_id']);
            }
        }
    }

    public function test_cada_fila_trae_lo_que_la_ui_necesita_para_rotularse(): void
    {
        $r = $this->buscar('parinas')[0];

        foreach (['type', 'district_id', 'province_id', 'department_id', 'name',
                  'province_name', 'department_name', 'context', 'label'] as $campo) {
            $this->assertArrayHasKey($campo, $r);
        }

        $this->assertSame('Pariñas', $r['name']);
        $this->assertSame('Talara', $r['province_name']);
        $this->assertSame('Piura', $r['department_name']);
        $this->assertSame('Distrito · Talara · Piura', $r['context']);
        // `label` es el contrato que consume el widget: no se puede cambiar
        // sin tocar el frontend.
        $this->assertSame('Pariñas — Talara, Piura', $r['label']);
    }

    public function test_el_ubigeo_devuelto_es_jerarquicamente_coherente(): void
    {
        // Nadie debe poder guardar un distrito que no pertenece a su provincia.
        foreach (['talara', 'lima', 'santa rosa', 'hua'] as $q) {
            foreach ($this->buscar($q) as $r) {
                $this->assertStringStartsWith($r['province_id'], $r['district_id']);
                $this->assertStringStartsWith($r['department_id'], $r['province_id']);
            }
        }
    }

    // ── Sin resultados y límites ─────────────────────────────────────────

    /** @dataProvider busquedasVacias */
    public function test_lo_que_no_existe_devuelve_vacio(string $q): void
    {
        $this->assertSame([], $this->buscar($q, true));
    }

    public function busquedasVacias(): array
    {
        // Menos de dos letras se descarta a propósito: «a» coincide con medio
        // catálogo y la lista no ayudaría a nadie.
        return [['xxxxxxxx'], ['a'], [''], ['   '], ['talaraa'], ['zzz']];
    }

    public function test_el_limite_se_respeta(): void
    {
        $this->assertLessThanOrEqual(3, count($this->buscar('san', true, 3)));
        $this->assertLessThanOrEqual(1, count($this->buscar('lima', true, 1)));
    }

    public function test_no_se_repite_un_distrito_alcanzado_por_dos_caminos(): void
    {
        // «Piura» coincide como departamento, como provincia Y como distrito:
        // el distrito no puede salir dos veces.
        $ids = array_column($this->buscar('piura'), 'district_id');
        $this->assertSame(array_unique($ids), $ids);
    }

    // ── Normalización ────────────────────────────────────────────────────

    /** @dataProvider normalizaciones */
    public function test_normalize(string $entrada, string $esperado): void
    {
        $this->assertSame($esperado, UbigeoSearch::normalize($entrada));
    }

    public function normalizaciones(): array
    {
        return [
            ['PARIÑAS', 'parinas'],
            ['Máncora', 'mancora'],
            ['Los Órganos', 'los organos'],
            ['  San   Martín  ', 'san martin'],
            ['Anco_Huallo', 'anco huallo'],
            ['HUÁNUCO', 'huanuco'],
            ['Prov. Const. del Callao', 'prov const del callao'],
            ['', ''],
        ];
    }
}
