<?php

namespace Tests\Unit\Marketplace;

use App\Services\Marketplace\ProductFeedFormatter;
use App\Services\Marketplace\ShippingSummaryText as T;
use App\Services\Tenant\StoreShipping;
use Tests\TestCase;

/**
 * La política de envío de una tienda contada al comprador. Una sola redacción
 * para la ficha del marketplace, el carrito, el checkout y la tienda online.
 * No tocan BD.
 */
class ShippingSummaryTextTest extends TestCase
{
    private function s(array $over = []): array
    {
        return array_replace_recursive([
            'v'        => 1,
            'lima'     => ['mode' => 'fixed', 'cost' => 10.0, 'days' => [1, 2]],
            'province' => ['mode' => 'destination', 'cost' => null, 'agency_fee' => 0.0, 'handling' => 1, 'transit' => [2, 5], 'agencies' => ['Shalom', 'Olva Courier']],
            'pickup'   => null,
            'free_from' => null,
            'cutoff'   => null,
        ], $over);
    }

    public function test_sin_politica_no_promete_nada(): void
    {
        $this->assertSame([], T::lines(null));
        $this->assertSame([], T::notes(null));
        $this->assertNull(T::badge(null));
        $this->assertSame('A coordinar con la tienda', T::short(null));
    }

    public function test_lima_costo_fijo_con_plazo(): void
    {
        $l = T::lines($this->s())[0];
        $this->assertSame('Lima y Callao', $l['title']);
        $this->assertSame('S/ 10.00', $l['price']);
        $this->assertSame('Llega en 1 a 2 días hábiles', $l['detail']);
        $this->assertFalse($l['free']);
    }

    public function test_provincias_pago_en_destino_explica_quien_paga_que(): void
    {
        $p = T::lines($this->s())[1];
        $this->assertSame('Pago en destino', $p['price']);
        $this->assertSame('Llevarlo a la agencia es gratis · Sale a la agencia en 1 día hábil + 2 a 5 días hábiles de viaje · Vía Shalom, Olva Courier', $p['detail']);

        $cobra = T::lines($this->s(['province' => ['agency_fee' => 15.0]]))[1];
        $this->assertStringContainsString('Llevarlo a la agencia: S/ 15.00', $cobra['detail']);
    }

    public function test_a_cotizar_con_y_sin_desde(): void
    {
        $this->assertSame('A cotizar, desde S/ 8.00', T::lines($this->s(['lima' => ['mode' => 'quote', 'cost' => 8]]))[0]['price']);
        $this->assertSame('A cotizar', T::lines($this->s(['lima' => ['mode' => 'quote', 'cost' => null]]))[0]['price']);
    }

    public function test_recojo_y_notas(): void
    {
        $s = $this->s(['pickup' => ['district' => 'Surco', 'hours' => 24], 'free_from' => 150, 'cutoff' => '18:00']);
        $r = T::lines($s)[2];
        $this->assertSame('Recojo en Surco', $r['title']);
        $this->assertSame('Gratis', $r['price']);
        $this->assertSame('Listo en 24 horas', $r['detail']);

        $notas = T::notes($s);
        $this->assertSame('Envío gratis en compras desde S/ 150.00 (el flete a provincias se paga en destino)', $notas[0]);
        $this->assertSame('Pedidos antes de las 18:00 salen el mismo día hábil', $notas[1]);
    }

    public function test_una_sola_etiqueta_en_la_card_y_solo_si_es_cierta(): void
    {
        $this->assertNull(T::badge($this->s()));
        $this->assertSame('Envío gratis en Lima', T::badge($this->s(['lima' => ['mode' => 'free']])));
        $this->assertSame('Envío gratis a todo el Perú', T::badge($this->s(['lima' => ['mode' => 'free'], 'province' => ['mode' => 'free']])));
    }

    public function test_linea_corta_del_carrito(): void
    {
        $this->assertSame('Lima S/ 10.00 · Provincias pago en destino', T::short($this->s()));
        $this->assertSame('Lima gratis · Provincias a cotizar, desde S/ 20.00', T::short($this->s(['lima' => ['mode' => 'free'], 'province' => ['mode' => 'quote', 'cost' => 20]])));
    }

    public function test_el_mismo_dia_y_un_solo_dia(): void
    {
        $this->assertSame('Llega en el mismo día', T::lines($this->s(['lima' => ['days' => [0, 0]]]))[0]['detail']);
        $this->assertSame('Llega en 1 día hábil', T::lines($this->s(['lima' => ['days' => [1, 1]]]))[0]['detail']);
    }

    public function test_lima_es_lima_metropolitana_y_callao_no_todo_el_departamento(): void
    {
        $this->assertTrue(StoreShipping::isLima('150131'));   // San Isidro
        $this->assertTrue(StoreShipping::isLima('070101'));   // Callao
        $this->assertFalse(StoreShipping::isLima('150801'));  // Huacho (Huaura): provincia
        $this->assertFalse(StoreShipping::isLima('040101'));  // Arequipa
        $this->assertTrue(StoreShipping::isLima('15'));       // solo departamento: se asume Lima
        $this->assertFalse(StoreShipping::isLima(null));
    }

    public function test_feed_solo_declara_lo_que_es_cierto_en_todo_el_pais(): void
    {
        $this->assertSame('', ProductFeedFormatter::shippingXml(null));

        $parcial = ProductFeedFormatter::shippingXml($this->s(['lima' => ['mode' => 'free']]));
        $this->assertStringNotContainsString('<g:shipping>', $parcial, 'gratis solo en Lima no es gratis en todo el Perú');
        $this->assertStringContainsString('<g:max_handling_time>1</g:max_handling_time>', $parcial);

        $todo = ProductFeedFormatter::shippingXml($this->s(['lima' => ['mode' => 'free'], 'province' => ['mode' => 'free']]));
        $this->assertStringContainsString('<g:price>0.00 PEN</g:price>', $todo);
    }
}
