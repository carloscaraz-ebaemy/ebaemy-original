<?php

namespace Tests\Unit\Marketplace;

use App\Services\Marketplace\SearchSpellRescue;
use PHPUnit\Framework\TestCase;

/**
 * El rescate de búsquedas con errata.
 *
 * Lo que de verdad hay que proteger aquí no es que corrija, sino que **no
 * corrija mal**: una corrección equivocada manda al comprador a un producto
 * que no tiene nada que ver, y eso es peor que la pantalla vacía, que al
 * menos dice la verdad. Probando contra el catálogo real aparecieron tres
 * correcciones absurdas (zapatila→zapatera, poloo→pollo, cartea→parte) y
 * estos tests fijan las reglas que las descartan.
 */
class SearchSpellRescueTest extends TestCase
{
    /** Un rescate con vocabulario fijo, sin base de datos. */
    private function rescate(array $vocabulario): SearchSpellRescue
    {
        return new class($vocabulario) extends SearchSpellRescue {
            public function __construct(private array $vocab) {}

            protected function vocabulario(): array
            {
                return $this->vocab;
            }
        };
    }

    // ── Lo que sí debe corregir ───────────────────────────────────────────

    public function test_corrige_una_errata_evidente()
    {
        $svc = $this->rescate(['zapatillas' => 5, 'maceta' => 3]);

        $this->assertSame('zapatillas', $svc->suggest('zapatillaa'));
        $this->assertSame('maceta', $svc->suggest('macetta'));
    }

    public function test_corrige_palabra_a_palabra_en_una_frase()
    {
        $svc = $this->rescate(['planta' => 9, 'artificiales' => 7]);

        $this->assertSame('planta artificiales', $svc->suggest('plantta artificiale'));
    }

    /**
     * El caso que motivó el cambio de criterio: «zapatera» (un mueble) está a
     * la misma distancia que «zapatillas» y aparecía más en el catálogo, así
     * que ganaba. Gana el que comparte más principio.
     */
    public function test_entre_dos_igual_de_cercanas_gana_la_del_mismo_principio()
    {
        // zapatera es más frecuente a propósito: antes eso bastaba para ganar.
        $svc = $this->rescate(['zapatera' => 50, 'zapatillas' => 2]);

        $this->assertSame('zapatillas', $svc->suggest('zapatila'));
    }

    // ── Lo que NO debe corregir ───────────────────────────────────────────

    /** Si la palabra ya existe en el catálogo, no hay nada que corregir. */
    public function test_no_toca_lo_que_ya_existe()
    {
        $svc = $this->rescate(['zapatillas' => 5]);

        $this->assertNull($svc->suggest('zapatillas'));
    }

    /** Sin un principio en común no es una errata, es otra palabra. */
    public function test_no_corrige_hacia_una_palabra_que_empieza_distinto()
    {
        $svc = $this->rescate(['parte' => 20]);

        $this->assertNull($svc->suggest('cartea'));
    }

    /**
     * «poloo» → «pollo» comparte sólo tres caracteres de principio. El
     * rescate prefiere quedarse corto antes que mandar a otro producto.
     */
    public function test_prefiere_no_corregir_antes_que_corregir_mal()
    {
        $svc = $this->rescate(['pollo' => 30]);

        $this->assertNull($svc->suggest('poloo'));
    }

    /** Lo que no se parece a nada del catálogo no se inventa. */
    public function test_no_inventa_cuando_no_hay_nada_parecido()
    {
        $svc = $this->rescate(['planta' => 9, 'maceta' => 4]);

        $this->assertNull($svc->suggest('xyzqwk'));
        $this->assertNull($svc->suggest('audifnos'));
    }

    /** En palabras muy cortas, un carácter ya es otra palabra distinta. */
    public function test_no_corrige_palabras_demasiado_cortas()
    {
        $svc = $this->rescate(['mesa' => 10]);

        $this->assertNull($svc->suggest('isa'));
    }

    public function test_sin_vocabulario_no_corrige_nada()
    {
        $this->assertNull($this->rescate([])->suggest('zapatillaa'));
    }

    public function test_una_consulta_vacia_no_rompe_nada()
    {
        $svc = $this->rescate(['planta' => 3]);

        $this->assertNull($svc->suggest(''));
        $this->assertNull($svc->suggest('   '));
        $this->assertNull($svc->suggest(null));
    }

    /**
     * La corrección se compara ya normalizada, así que una búsqueda que sólo
     * difiere en tildes o mayúsculas no debe dar una «corrección» que es la
     * misma palabra: sería un redirect inútil.
     */
    public function test_no_propone_un_cambio_que_no_cambia_nada()
    {
        $svc = $this->rescate(['planta' => 3]);

        $this->assertNull($svc->suggest('planta'));
    }
}
