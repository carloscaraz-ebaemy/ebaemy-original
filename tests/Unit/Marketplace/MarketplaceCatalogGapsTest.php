<?php

namespace Tests\Unit\Marketplace;

use App\Console\Commands\MarketplaceCatalogGaps;
use PHPUnit\Framework\TestCase;

/**
 * La regla que propone categoría oficial para un producto que no la tiene.
 *
 * Lo que importa aquí es que distinga una propuesta fiable de una dudosa, no
 * que acierte siempre: el comando nunca escribe, deja los comandos listos
 * para que una persona los revise. Lo peligroso sería presentar un falso
 * positivo con la misma pinta que un acierto.
 */
class MarketplaceCatalogGapsTest extends TestCase
{
    /** Expone la regla, que en el comando es protegida. */
    private function comando(): MarketplaceCatalogGaps
    {
        return new class extends MarketplaceCatalogGaps {
            public function proponer(?string $titulo, ?string $catTienda, $categorias): ?array
            {
                return $this->mejorCategoria($titulo, $catTienda, $categorias);
            }
        };
    }

    /** Categorías oficiales tal como las arma el comando. */
    private function categorias(): array
    {
        return [
            ['id' => 3,   'name' => 'Plantas artificiales', 'slug' => 'x', 'tokens' => ['plantas', 'artificiales']],
            ['id' => 144, 'name' => 'Macetas y jardineras', 'slug' => 'x', 'tokens' => ['macetas', 'jardineras']],
            ['id' => 60,  'name' => 'Protector solar',      'slug' => 'x', 'tokens' => ['protector', 'solar']],
            ['id' => 141, 'name' => 'Manteles',             'slug' => 'x', 'tokens' => ['manteles']],
        ];
    }

    /**
     * El caso que destapó la auditoría: 44 productos «sin categoría» que la
     * tienda ya tenía clasificados en su propio catálogo. Sólo había que
     * traducirlo al árbol oficial.
     */
    public function test_la_categoria_de_la_tienda_manda_sobre_el_titulo()
    {
        $r = $this->comando()->proponer(
            'Árbol Cerezo ROJO 180 cm (Sin Base)',   // el título no dice «planta»
            'Plantas Artificiales',                  // pero la tienda ya lo clasificó
            $this->categorias()
        );

        $this->assertSame(3, $r['id']);
        $this->assertSame('categoria de la tienda', $r['fuente']);
        $this->assertSame(2, $r['aciertos']);
        $this->assertSame(2, $r['de']);
    }

    public function test_sin_categoria_de_tienda_todavia_propone_por_el_titulo()
    {
        $r = $this->comando()->proponer('Manteles bordados', null, $this->categorias());

        $this->assertSame(141, $r['id']);
        $this->assertSame('titulo', $r['fuente']);
    }

    /**
     * «Protector de mueble» → «Protector solar» es un falso positivo real del
     * catálogo. No se oculta (puede ser lo único que haya), pero tiene que
     * quedar marcado como cobertura parcial y decir qué palabra lo provocó,
     * que es lo que permite descartarlo de un vistazo.
     */
    public function test_un_falso_positivo_queda_marcado_como_parcial_y_con_su_motivo()
    {
        $r = $this->comando()->proponer('Protector de mueble en L', null, $this->categorias());

        $this->assertSame(60, $r['id']);
        $this->assertSame(1, $r['aciertos']);
        $this->assertSame(2, $r['de']);           // cubre 1 de 2: hay que mirarlo
        $this->assertSame(['protector'], $r['por']);
    }

    /** Lo que no se parece a ninguna categoría no se fuerza. */
    public function test_no_propone_nada_cuando_no_hay_parecido()
    {
        $this->assertNull(
            $this->comando()->proponer('Cable HDMI 2 metros', 'Accesorios', $this->categorias())
        );
    }

    public function test_un_producto_sin_titulo_ni_categoria_no_rompe_nada()
    {
        $this->assertNull($this->comando()->proponer(null, null, $this->categorias()));
        $this->assertNull($this->comando()->proponer('', '', $this->categorias()));
    }

    /** Tildes y mayúsculas no pueden impedir la coincidencia. */
    public function test_la_coincidencia_ignora_tildes_y_mayusculas()
    {
        $r = $this->comando()->proponer('Maceta grande', 'MACETAS', $this->categorias());

        $this->assertSame(144, $r['id']);
        $this->assertSame('categoria de la tienda', $r['fuente']);
    }

    /**
     * Cubrir la categoría entera vale más que coincidir en una palabra: entre
     * «Manteles» (1/1) y una categoría de dos palabras a medias, gana la que
     * se cubre del todo.
     */
    public function test_prefiere_la_categoria_que_queda_cubierta_entera()
    {
        $r = $this->comando()->proponer('Manteles protector', null, $this->categorias());

        $this->assertSame(141, $r['id']);
    }
}
