<?php

namespace Tests\Unit;

use App\Services\Tenant\WhatsAppOfferCampaignService;
use Tests\TestCase;

class WhatsAppCampaignTextTest extends TestCase
{
    private function productos(): \Illuminate\Support\Collection
    {
        return collect([
            ['item_id' => 1, 'description' => 'Pino Americano 2.10 m', 'regular_price' => 550.0, 'offer_price' => 499.0, 'url' => 'https://t.ebaemy.com/ecommerce/item/pino'],
            ['item_id' => 2, 'description' => 'Luces LED 35 m', 'regular_price' => 45.0, 'offer_price' => null, 'url' => 'https://t.ebaemy.com/ecommerce/item/luces'],
        ]);
    }

    public function test_reemplaza_el_nombre_y_lista_productos_con_oferta_y_enlace()
    {
        $svc = new WhatsAppOfferCampaignService();
        $t = $svc->textoCampana('MARÍA LUISA PÉREZ', 'Hola {nombre}, llegaron los árboles:', $this->productos(), 'Alasitas');

        $this->assertStringStartsWith('Hola María, llegaron los árboles:', $t);
        $this->assertStringContainsString('• Pino Americano 2.10 m: S/ 499.00 (antes S/ 550.00)', $t);
        $this->assertStringContainsString('• Luces LED 35 m: S/ 45.00', $t);
        $this->assertStringContainsString('https://t.ebaemy.com/ecommerce/item/pino', $t);
        $this->assertStringEndsWith('Si no quieres recibir más avisos, responde BAJA.', $t);
    }

    public function test_sin_nombre_ni_texto_usa_un_saludo_generico()
    {
        $svc = new WhatsAppOfferCampaignService();
        $t = $svc->textoCampana('', '', $this->productos(), 'Alasitas');

        $this->assertStringStartsWith('Hola, te compartimos estas novedades de Alasitas.', $t);
        $this->assertStringNotContainsString('{nombre}', $t);
    }

    public function test_sin_nombre_no_deja_la_coma_colgando()
    {
        $svc = new WhatsAppOfferCampaignService();
        $t = $svc->textoCampana(null, 'Hola {nombre}, mira esto:', $this->productos(), 'Alasitas');

        $this->assertStringStartsWith('Hola, mira esto:', $t);
    }
}
