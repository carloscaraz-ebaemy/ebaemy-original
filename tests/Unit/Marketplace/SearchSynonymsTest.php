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

    // ── Erratas que suenan igual ──────────────────────────────────────────

    /**
     * El caso reportado el 2026-10-05. `expand()` lo consumen a la vez el
     * filtro (`scopeSearch`) y el orden (`textRelevanceSql`), así que basta con
     * que la grafía correcta salga de aquí para que la búsqueda la encuentre.
     */
    public function test_una_errata_que_suena_igual_alcanza_la_grafia_del_catalogo()
    {
        $this->assertContains('cojines', SearchSynonyms::expand('cogines'));
        $this->assertContains('vaso', SearchSynonyms::expand('baso'));
        $this->assertContains('zapato', SearchSynonyms::expand('sapato'));
        $this->assertContains('llave', SearchSynonyms::expand('yave'));
    }

    /**
     * El catálogo guarda «Cojín» y «Cojines» según el vendedor, así que una
     * errata en plural tiene que alcanzar también el singular bien escrito.
     */
    public function test_una_errata_en_plural_alcanza_el_singular_correcto()
    {
        $v = SearchSynonyms::expand('cogines');

        $this->assertContains('cojines', $v);
        $this->assertContains('cojin', $v);
    }

    /** Una palabra mal escrita debe llegar igual de lejos que la bien escrita. */
    public function test_una_errata_tambien_alcanza_los_sinonimos()
    {
        // «sapatillas» → «zapatillas» → tenis/calzado.
        $v = SearchSynonyms::expand('sapatillas');

        $this->assertContains('zapatillas', $v);
        $this->assertContains('tenis', $v);
    }

    /**
     * `spellings()` es la mitad que usa la tienda del tenant: la MISMA palabra
     * escrita de otro modo, nunca palabras de significado parecido. Si se le
     * colaran sinónimos, el storefront empezaría a devolver productos que el
     * comprador no nombró, y eso no es lo que se pidió.
     */
    public function test_spellings_no_trae_sinonimos_de_significado()
    {
        $v = SearchSynonyms::spellings('asiento');

        $this->assertContains('asiento', $v);
        $this->assertNotContains('silla', $v);
        $this->assertNotContains('taburete', $v);

        // Pero sí la errata que suena igual.
        $this->assertContains('aciento', $v);
    }

    /** La palabra tecleada va siempre primera: se amplía, no se sustituye. */
    public function test_lo_tecleado_va_primero()
    {
        $this->assertSame('cogines', SearchSynonyms::spellings('cogines')[0]);
        $this->assertSame('cogines', SearchSynonyms::expand('cogines')[0]);
    }

    /**
     * Cada variante son cuatro `LIKE` más en el SQL de relevancia, por hasta 5
     * tokens. El tope evita que una palabra con muchas ambigüedades infle la
     * consulta.
     */
    public function test_expand_respeta_un_tope()
    {
        foreach (['zapatillas', 'cabesa', 'cogines', 'inalanbrico'] as $palabra) {
            $this->assertLessThanOrEqual(12, count(SearchSynonyms::expand($palabra)));
        }
    }

    public function test_sigue_sin_devolver_duplicados_ni_vacios_con_erratas()
    {
        foreach (['cogines', 'sapatillas', 'baso', 'inalanbrico'] as $palabra) {
            $v = SearchSynonyms::expand($palabra);

            $this->assertSame(array_values(array_unique($v)), $v, "duplicados en «{$palabra}»");
            $this->assertNotContains('', $v);
        }
    }
}
