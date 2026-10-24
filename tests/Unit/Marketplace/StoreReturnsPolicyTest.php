<?php

namespace Tests\Unit\Marketplace;

use App\Services\Marketplace\ShippingSummaryText as T;
use App\Services\Tenant\StoreReturnsPolicy;
use Tests\TestCase;

/**
 * La política de cambios se genera a partir de datos: nunca debe publicarse con
 * huecos «[7]» ni dejar pasar HTML de la tienda. No tocan BD (el nombre de la
 * tienda cae a «nuestra tienda» sin conexión).
 */
class StoreReturnsPolicyTest extends TestCase
{
    private function datos(array $over = []): array
    {
        return array_replace([
            'defect_days' => 7, 'change_enabled' => true, 'change_days' => 15, 'change_shipping' => 'customer',
            'refund_days' => 5, 'excluded' => 'ropa interior', 'contact_whatsapp' => '987654321',
            'contact_email' => 'ventas@tienda.pe',
        ], $over);
    }

    public function test_el_texto_generado_no_tiene_huecos(): void
    {
        $html = (new StoreReturnsPolicy())->render($this->datos());

        $this->assertStringNotContainsString('[', $html);
        $this->assertStringContainsString('dentro de los <strong>7 días</strong>', $html);
        $this->assertStringContainsString('dentro de los <strong>15 días</strong> de recibido', $html);
        $this->assertStringContainsString('<strong>lo paga el cliente</strong>', $html);
        $this->assertStringContainsString('<strong>5 días hábiles</strong>', $html);
        $this->assertStringContainsString('<strong>No aceptan cambio:</strong> ropa interior.', $html);
        $this->assertStringContainsString('WhatsApp al <strong>987654321</strong> o a <strong>ventas@tienda.pe</strong>', $html);
        $this->assertStringContainsString('garantía legal', $html);
    }

    public function test_sin_cambio_por_talla_lo_dice_y_no_habla_de_quien_paga_ese_envio(): void
    {
        $html = (new StoreReturnsPolicy())->render($this->datos(['change_enabled' => false, 'excluded' => '']));

        $this->assertStringContainsString('No aceptamos cambios por talla, color o modelo', $html);
        $this->assertStringNotContainsString('Si es un cambio por talla', $html);
        $this->assertStringNotContainsString('No aceptan cambio:', $html);
    }

    public function test_lo_que_escribe_la_tienda_se_escapa(): void
    {
        $html = (new StoreReturnsPolicy())->render($this->datos(['excluded' => '<script>alert(1)</script>']));

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_sin_contacto_no_deja_la_frase_a_medias(): void
    {
        $html = (new StoreReturnsPolicy())->render($this->datos(['contact_whatsapp' => '', 'contact_email' => '']));

        $this->assertStringContainsString('Escríbenos por nuestros canales de contacto', $html);
    }

    public function test_linea_de_cambios_en_la_ficha(): void
    {
        $this->assertNull(T::returns(null));
        $this->assertNull(T::returns(['returns' => null]));
        $this->assertSame('Cambios por falla hasta 7 días', T::returns(['returns' => ['defect_days' => 7, 'change_days' => null]]));
        $this->assertSame('Cambios por falla hasta 7 días · por talla o color hasta 1 día', T::returns(['returns' => ['defect_days' => 7, 'change_days' => 1]]));
    }
}
