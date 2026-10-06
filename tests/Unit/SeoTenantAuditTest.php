<?php

namespace Tests\Unit;

use App\Console\Commands\SeoTenantAudit;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Lo que este comando escribe acaba en los resultados de Google, así que los
 * dos riesgos que hay que fijar son: que la descripción salga cortada a mitad
 * de palabra, y que `--rewrite` pise lo que un vendedor escribió a mano.
 *
 * Ambos aparecieron de verdad: la primera pasada en producción (2026-10-06)
 * dejó «...para el cuidado de la ropa l...» en las dos tiendas con catálogo
 * importado de Saga, cuyas categorías son enumeraciones larguísimas.
 */
class SeoTenantAuditTest extends TestCase
{
    private function invocar(string $metodo, array $args)
    {
        $m = new ReflectionMethod(SeoTenantAudit::class, $metodo);
        $m->setAccessible(true);

        return $m->invoke(new SeoTenantAudit(), ...$args);
    }

    private function describir(string $nombre, string $cats): string
    {
        return $this->invocar('describir', [$nombre, $cats]);
    }

    // ── Longitud y cortes ─────────────────────────────────────────────────

    /**
     * 155 caracteres es donde Google corta la descripción en los resultados.
     *
     * @dataProvider tiendas
     */
    public function test_la_descripcion_cabe_en_el_limite(string $nombre, string $cats): void
    {
        $this->assertLessThanOrEqual(155, mb_strlen($this->describir($nombre, $cats)));
    }

    /**
     * En vez de recortar la frase se quitan categorías enteras, así que no
     * puede quedar ningún «...» en medio.
     *
     * @dataProvider tiendas
     */
    public function test_la_descripcion_no_sale_cortada(string $nombre, string $cats): void
    {
        $this->assertStringNotContainsString(
            '...',
            $this->describir($nombre, $cats),
            'una descripción cortada a mitad de palabra no la lee nadie'
        );
    }

    /** @dataProvider tiendas */
    public function test_la_descripcion_nombra_la_tienda(string $nombre, string $cats): void
    {
        $this->assertStringContainsString($nombre, $this->describir($nombre, $cats));
    }

    public static function tiendas(): array
    {
        return [
            'categorias de Saga, larguisimas' => [
                'CAROLAY IMPORT HOME',
                'Accesorios / repuestos de utensilios para cocinar, Accesorios / repuestos para aparatos para el cuidado de la ropa limpia, Bandejas',
            ],
            'nombre largo y categorias largas' => [
                'IMPORTACIONES DEYWA SOCIEDAD ANONIMA CERRADA',
                'Accesorios / repuestos para fotografía, Accesorios de decoración, Accesorios de flash para cámaras',
            ],
            'caso normal' => [
                'LIA DECORACIONES',
                'Plantas artificiales, Floreros y jarrones, Cuadros y láminas',
            ],
            'sin categorias' => ['VALENTINA IMPORTACIONES', ''],
            'una sola categoria corta' => ['MYKA', 'Bandejas'],
        ];
    }

    /** Si caben todas las categorías, se usan todas: no se tira información. */
    public function test_usa_todas_las_categorias_cuando_caben(): void
    {
        $desc = $this->describir('LIA DECORACIONES', 'Plantas artificiales, Floreros y jarrones, Cuadros y láminas');

        $this->assertStringContainsString('Plantas artificiales', $desc);
        $this->assertStringContainsString('Floreros y jarrones', $desc);
        $this->assertStringContainsString('Cuadros y láminas', $desc);
    }

    /** Y si no caben todas, se queda con las que entren enteras. */
    public function test_descarta_categorias_hasta_que_cabe(): void
    {
        $desc = $this->describir(
            'CAROLAY IMPORT HOME',
            'Accesorios / repuestos de utensilios para cocinar, Accesorios / repuestos para aparatos para el cuidado de la ropa limpia, Bandejas'
        );

        $this->assertStringContainsString('Accesorios / repuestos de utensilios para cocinar', $desc);
        $this->assertStringNotContainsString('Bandejas', $desc);
    }

    /** Texto para personas: con tildes. */
    public function test_el_texto_lleva_tildes(): void
    {
        $this->assertStringContainsString('catálogo', $this->describir('MYKA', 'Bandejas'));
    }

    // ── Qué se puede sobreescribir ────────────────────────────────────────

    /**
     * `--rewrite` solo puede tocar lo que generó el propio comando. Acertar
     * aquí es lo que separa «propagar una mejora» de «borrar el trabajo del
     * vendedor».
     *
     * @dataProvider descripciones
     */
    public function test_distingue_lo_propio_de_lo_escrito_a_mano(string $desc, bool $esNuestra): void
    {
        $this->assertSame($esNuestra, $this->invocar('laEscribimosNosotros', [$desc]));
    }

    public static function descripciones(): array
    {
        return [
            'plantilla sin tilde (primeras pasadas)' => [
                'Tienda online de LIA DECORACIONES: Plantas. Mira el catalogo y compra desde tu celular.', true,
            ],
            'plantilla con tilde' => [
                'Tienda online de X: Y. Mira el catálogo y compra desde tu celular.', true,
            ],
            'plantilla sin nombre comercial' => [
                'Catálogo de Plantas artificiales. Compra online desde tu celular.', true,
            ],
            'la de fabrica del layout' => ['Bienvenido a nuestra tienda.', false],
            'escrita por el vendedor' => [
                'Somos la mejor floristería de Lima, envíos el mismo día.', false,
            ],
            'empieza parecido pero es del vendedor' => [
                'Tienda online de verdad, atendemos por WhatsApp.', false,
            ],
            'acaba parecido pero es del vendedor' => [
                'Compra lo que quieras desde tu celular.', false,
            ],
            'vacia' => ['', false],
        ];
    }

    // ── Recorte por palabras ──────────────────────────────────────────────

    public function test_recorta_por_palabras_completas(): void
    {
        $original = 'Accesorios para el cuidado de la ropa limpia';
        $corte    = $this->invocar('recortar', [$original, 30]);

        $this->assertLessThanOrEqual(33, mb_strlen($corte));
        $this->assertStringEndsWith('...', $corte);

        // Lo que de verdad importa: toda palabra que quede tiene que estar
        // entera en el original. Si «cuidado» se hubiera partido en «cuida»,
        // esa palabra ya no existiria ahi.
        $palabrasOriginal = explode(' ', $original);

        foreach (explode(' ', rtrim($corte, '.')) as $palabra) {
            $this->assertContains(
                $palabra,
                $palabrasOriginal,
                "«{$palabra}» salio partida: no es una palabra del original"
            );
        }
    }

    public function test_no_recorta_lo_que_ya_cabe(): void
    {
        $this->assertSame('MYKA', $this->invocar('recortar', ['MYKA', 60]));
    }

    /** Una sola palabra más larga que el límite sí se corta: no hay alternativa. */
    public function test_una_palabra_larguisima_se_corta(): void
    {
        $corte = $this->invocar('recortar', ['electrodomesticosindustriales', 12]);

        $this->assertLessThanOrEqual(15, mb_strlen($corte));
        $this->assertStringEndsWith('...', $corte);
    }
}
