<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * El POST del checkout del marketplace RESPONDE.
 *
 * Del 17 al 25 de noviembre de 2026 todo POST /marketplace/checkout dio 500:
 * el controlador llamaba a `MarketplaceCheckoutRules` sin `use`. El test
 * unitario de esa clase pasaba (la importaba él mismo) y nadie pudo comprar
 * durante ocho días sin que ninguna prueba lo dijera. Esto es lo mínimo que
 * habría avisado: el endpoint real, por HTTP, nunca revienta.
 *
 * No necesita datos: con el formulario vacío debe devolver la redirección de
 * validación, y con datos válidos y el carrito vacío debe volver atrás con el
 * aviso de carrito vacío. Un error fatal en cualquier punto del camino da 500
 * y el test falla.
 */
class MarketplaceCheckoutPostTest extends TestCase
{
    private function url(): string
    {
        return rtrim((string) config('app.url'), '/') . '/marketplace/checkout';
    }

    public function test_formulario_vacio_devuelve_validacion_no_500(): void
    {
        $r = $this->from($this->url())->post($this->url(), []);

        $r->assertStatus(302);
        $r->assertSessionHasErrors(['customer_name', 'customer_phone', 'delivery_address']);
    }

    public function test_datos_validos_con_carrito_vacio_vuelve_con_aviso_no_500(): void
    {
        $r = $this->from($this->url())->post($this->url(), [
            'customer_name'       => 'Comprador de prueba',
            'customer_doc_type'   => 'DNI',
            'customer_doc_number' => '12345678',
            'customer_phone'      => '987654321',
            'customer_email'      => 'prueba@example.com',
            'delivery_address'    => 'Av. Prueba 123',
            'delivery_department' => 'Lima',
            'delivery_province'   => 'Lima',
            'delivery_district'   => 'Miraflores',
        ]);

        $r->assertStatus(302);
        $r->assertSessionHasErrors();
        $errores = implode(' ', array_map('strval', array_merge(...array_values(session('errors')->toArray()))));
        $this->assertStringContainsStringIgnoringCase('carrito', $errores);
    }
}
