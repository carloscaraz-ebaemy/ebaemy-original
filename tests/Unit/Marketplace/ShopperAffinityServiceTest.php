<?php

namespace Tests\Unit\Marketplace;

use App\Models\System\MarketplaceListing;
use App\Services\Marketplace\ShopperAffinityService;
use Tests\TestCase;

/**
 * Tests del ordenado de escaparates por afinidad del visitante.
 * No tocan BD: los listings se construyen en memoria y las señales van
 * por sesion (array driver en testing).
 */
class ShopperAffinityServiceTest extends TestCase
{
    private function svc(): ShopperAffinityService
    {
        return new ShopperAffinityService();
    }

    private function offer(int $hostnameId, string $title, ?string $category = null, ?string $brand = null): MarketplaceListing
    {
        $l = new MarketplaceListing();
        $l->hostname_id   = $hostnameId;
        $l->title         = $title;
        $l->category_name = $category;
        $l->brand_name    = $brand;

        return $l;
    }

    public function test_sin_senales_devuelve_el_mismo_orden()
    {
        session()->flush();

        $offers = collect([
            $this->offer(1, 'Tomatodo motivacional'),
            $this->offer(2, 'Guerrero templario'),
            $this->offer(3, 'Polo deportivo'),
        ]);

        $out = $this->svc()->rankOffers($offers);

        $this->assertSame(
            ['Tomatodo motivacional', 'Guerrero templario', 'Polo deportivo'],
            $out->pluck('title')->all()
        );
    }

    public function test_lo_buscado_sube_la_oferta_afin()
    {
        session()->flush();
        $svc = $this->svc();
        $svc->pushQuery('mochila escolar');

        $offers = collect([
            $this->offer(1, 'Tomatodo motivacional'),
            $this->offer(2, 'Guerrero templario'),
            $this->offer(3, 'Mochila escolar reforzada'),
            $this->offer(4, 'Taza ceramica'),
        ]);

        $out = $this->svc()->rankOffers($offers);

        $this->assertSame('Mochila escolar reforzada', $out->first()->title);
        $this->assertCount(4, $out, 'reordena, no filtra');
    }

    public function test_la_palabra_entera_pesa_mas_que_el_fragmento()
    {
        session()->flush();
        $this->svc()->pushQuery('polo');

        $offers = collect([
            $this->offer(1, 'Espolon calcaneo plantilla'),
            $this->offer(2, 'Taza ceramica'),
            $this->offer(3, 'Polo deportivo algodon'),
        ]);

        $out = $this->svc()->rankOffers($offers);

        $this->assertSame('Polo deportivo algodon', $out->first()->title);
        $this->assertSame('Espolon calcaneo plantilla', $out->get(1)->title);
    }

    public function test_los_afines_intercalan_tiendas()
    {
        session()->flush();
        $this->svc()->pushQuery('mochila');

        // Tres mochilas de la tienda 1 y una de la tienda 2: la tienda 1 no
        // debe quedarse con todo el arranque del carrusel.
        $offers = collect([
            $this->offer(1, 'Mochila escolar A'),
            $this->offer(1, 'Mochila escolar B'),
            $this->offer(1, 'Mochila escolar C'),
            $this->offer(2, 'Mochila trekking'),
            $this->offer(3, 'Taza ceramica'),
        ]);

        $out = $this->svc()->rankOffers($offers);
        $shops = $out->pluck('hostname_id')->all();

        $this->assertSame(2, $shops[1], 'la segunda card debe ser de otra tienda');
        $this->assertSame([1, 2, 1, 1, 3], $shops);
    }

    public function test_lo_visto_tambien_cuenta_pero_menos_que_lo_buscado()
    {
        session()->flush();
        $this->svc()->pushQuery('taza');

        $viewed = collect([
            $this->offer(9, 'Mochila escolar reforzada', 'Mochilas'),
        ]);

        $offers = collect([
            $this->offer(1, 'Guerrero templario'),
            $this->offer(2, 'Mochila trekking', 'Mochilas'),
            $this->offer(3, 'Taza ceramica'),
        ]);

        $out = $this->svc()->rankOffers($offers, $viewed);

        $this->assertSame('Taza ceramica', $out->first()->title, 'lo buscado manda');
        $this->assertSame('Mochila trekking', $out->get(1)->title, 'lo visto sube por encima de lo indiferente');
    }

    public function test_sin_oferta_afin_no_se_toca_el_orden()
    {
        session()->flush();
        $this->svc()->pushQuery('refrigeradora');

        $offers = collect([
            $this->offer(1, 'Tomatodo motivacional'),
            $this->offer(2, 'Guerrero templario'),
            $this->offer(3, 'Taza ceramica'),
        ]);

        $out = $this->svc()->rankOffers($offers);

        $this->assertSame(
            ['Tomatodo motivacional', 'Guerrero templario', 'Taza ceramica'],
            $out->pluck('title')->all()
        );
    }

    public function test_las_queries_son_lru_sin_duplicados_y_con_tope()
    {
        session()->flush();
        $svc = $this->svc();

        foreach (['polo', 'mochila', 'taza', 'polo', 'reloj', 'gorra', 'lampara', 'cuaderno'] as $q) {
            $svc->pushQuery($q);
        }

        $queries = $svc->queries();

        $this->assertCount(ShopperAffinityService::MAX_QUERIES, $queries);
        $this->assertSame('cuaderno', $queries[0], 'la ultima busqueda va primera');
        $this->assertSame(array_unique($queries), $queries, 'sin duplicados');
    }

    public function test_ignora_terminos_demasiado_cortos()
    {
        session()->flush();
        $svc = $this->svc();
        $svc->pushQuery('de');  // ruido: no debe generar tokens
        $svc->pushQuery('a');   // bajo el minimo de pushQuery

        $offers = collect([
            $this->offer(1, 'Cuaderno de dibujo'),
            $this->offer(2, 'Taza de ceramica'),
            $this->offer(3, 'Reloj de pared'),
        ]);

        $out = $this->svc()->rankOffers($offers);

        $this->assertSame(
            ['Cuaderno de dibujo', 'Taza de ceramica', 'Reloj de pared'],
            $out->pluck('title')->all()
        );
    }

    public function test_menos_de_tres_ofertas_no_se_reordena()
    {
        session()->flush();
        $this->svc()->pushQuery('taza');

        $offers = collect([
            $this->offer(1, 'Guerrero templario'),
            $this->offer(2, 'Taza ceramica'),
        ]);

        $out = $this->svc()->rankOffers($offers);

        $this->assertSame('Guerrero templario', $out->first()->title);
    }
}
