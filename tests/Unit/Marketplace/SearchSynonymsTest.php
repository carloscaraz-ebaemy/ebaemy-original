<?php

namespace Tests\Unit\Marketplace;

use App\Services\System\SearchSynonyms;
use PHPUnit\Framework\TestCase;

/**
 * La expansión de un término de búsqueda: sinónimos y singular.
 *
 * El singular existe por una medición, no por teoría. El buscador casa con
 * `LIKE '%token%'`: «maceta» aparece dentro de «Macetas», pero «macetas» no
 * aparece dentro de «Maceta». En producción, con el mismo catálogo, «maceta»
 * devolvía 25 resultados y «macetas» sólo 3 — y la gente busca en plural.
 */
class SearchSynonymsTest extends TestCase
{
    // ── Singular ──────────────────────────────────────────────────────────

    public function test_quita_la_marca_de_plural_mas_comun()
    {
        $this->assertSame('maceta', SearchSynonyms::singular('macetas'));
        $this->assertSame('alfombra', SearchSynonyms::singular('alfombras'));
        $this->assertSame('cojin', SearchSynonyms::singular('cojines'));
        $this->assertSame('mantel', SearchSynonyms::singular('manteles'));
    }

    /**
     * En palabras cortas la terminación no es un plural, y recortarlas
     * generaría búsquedas de basura que casan con cualquier cosa.
     */
    public function test_no_recorta_palabras_cortas()
    {
        $this->assertNull(SearchSynonyms::singular('mes'));
        $this->assertNull(SearchSynonyms::singular('tres'));
        $this->assertNull(SearchSynonyms::singular('mas'));
    }

    public function test_una_palabra_en_singular_se_queda_como_esta()
    {
        $this->assertNull(SearchSynonyms::singular('maceta'));
        $this->assertNull(SearchSynonyms::singular('planta'));
    }

    // ── Expansión ─────────────────────────────────────────────────────────

    /**
     * Lo que el comprador escribió va SIEMPRE, y el singular se añade: esto
     * sólo puede sumar resultados, nunca quitar los que ya salían.
     */
    public function test_expandir_conserva_el_termino_tecleado_y_anade_el_singular()
    {
        $v = SearchSynonyms::expand('macetas');

        $this->assertContains('macetas', $v);
        $this->assertContains('maceta', $v);
    }

    public function test_sigue_expandiendo_sinonimos()
    {
        $v = SearchSynonyms::expand('asiento');

        $this->assertContains('silla', $v);
        $this->assertContains('asiento', $v);
    }

    /**
     * Buscando en plural también deben entrar los sinónimos del singular:
     * «zapatillas» tiene que alcanzar lo que alcanza «zapatilla».
     */
    public function test_el_plural_hereda_los_sinonimos_de_su_singular()
    {
        $v = SearchSynonyms::expand('audifonos');

        $this->assertContains('auriculares', $v);
    }

    public function test_las_tildes_no_cambian_la_expansion()
    {
        $this->assertSame(
            SearchSynonyms::expand('macetas'),
            SearchSynonyms::expand('MACETÁS')
        );
    }

    public function test_no_devuelve_duplicados_ni_vacios()
    {
        $v = SearchSynonyms::expand('zapatillas');

        $this->assertSame(array_values(array_unique($v)), $v);
        $this->assertNotContains('', $v);
    }
}
