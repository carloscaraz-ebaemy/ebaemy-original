<?php

namespace Tests\Unit\Marketplace;

use App\Services\Marketplace\ProductFeedFormatter as F;
use Tests\TestCase;

/**
 * Lo que el feed de Google/Meta/TikTok limpia antes de salir. Los casos son
 * los medidos en producción el 2026-10-24. No tocan BD.
 */
class ProductFeedFormatterTest extends TestCase
{
    public function test_quita_entidades_y_etiquetas_de_la_descripcion(): void
    {
        $html = 'MINI LAPTOP EDUCATIVA&nbsp;&nbsp;- Especial para ni&ntilde;os.<br>- Idiomas<p>Ingl&eacute;s</p>';

        $this->assertSame('MINI LAPTOP EDUCATIVA - Especial para niños. - Idiomas Inglés', F::text($html));
    }

    public function test_texto_guardado_ya_escapado_tampoco_deja_nbsp(): void
    {
        // Lo que llegaba al anuncio: «&amp;nbsp;» porque el texto ya venía escapado.
        $this->assertSame('Uno Dos', F::text('Uno&amp;nbsp;Dos'));
        $this->assertStringNotContainsString('nbsp', F::text("A\u{00A0}&nbsp;&amp;nbsp;B"));
    }

    public function test_los_bloques_no_se_pegan_al_quitar_etiquetas(): void
    {
        $this->assertSame('Uno Dos', F::text('<p>Uno</p><p>Dos</p>'));
    }

    public function test_respeta_el_largo_maximo(): void
    {
        $this->assertSame(150, mb_strlen(F::text(str_repeat('á', 400), 150)));
    }

    public function test_una_marca_real_se_conserva(): void
    {
        $this->assertSame('FOREVER 21', F::brand('FOREVER 21', 'CAROLAY IMPORT HOME'));
    }

    /** @dataProvider marcasQueNoSonMarca */
    public function test_sin_marca_real_usa_el_nombre_de_la_tienda(?string $marca): void
    {
        $this->assertSame('CAROLAY IMPORT HOME', F::brand($marca, 'CAROLAY IMPORT HOME'));
    }

    public static function marcasQueNoSonMarca(): array
    {
        return [[null], [''], ['OEM'], ['oem'], ['GENERICO'], ['Genérico'], ['GENÉRICA'], ['Sin marca'], ['N/A'], ['-']];
    }

    public function test_tienda_en_minusculas_sale_con_mayusculas_iniciales(): void
    {
        $this->assertSame('Floristería Pétalo & Encanto', F::brand('', 'floristería pétalo &amp; encanto'));
    }

    public function test_sin_marca_ni_tienda_no_inventa_nada(): void
    {
        $this->assertNull(F::brand('OEM', ''));
    }

    public function test_categoria_gana_el_prefijo_mas_largo(): void
    {
        $map = ['hogar' => 536, 'hogar/decoracion' => 696, 'hogar/decoracion/plantas-artificiales' => 6265];

        $this->assertSame(6265, F::resolve('hogar/decoracion/plantas-artificiales', $map));
        $this->assertSame(696, F::resolve('hogar/decoracion/espejos', $map), 'sin fila propia hereda la de su madre');
        $this->assertSame(536, F::resolve('hogar/bano', $map));
        $this->assertNull(F::resolve('servicios/profesionales', $map), 'servicios no va a Shopping');
        $this->assertNull(F::resolve(null, $map));
    }

    public function test_el_mapa_real_solo_usa_ids_numericos(): void
    {
        $map = require base_path('config/marketplace_google_categories.php');

        $this->assertNotEmpty($map);
        foreach ($map as $slug => $id) {
            $this->assertIsInt($id, $slug);
            $this->assertGreaterThan(0, $id, $slug);
            $this->assertSame(trim($slug, '/'), $slug, "$slug sin barras sobrantes");
        }
        $this->assertArrayNotHasKey('servicios', $map);
    }
}
