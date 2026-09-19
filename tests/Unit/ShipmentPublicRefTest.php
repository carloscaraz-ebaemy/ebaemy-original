<?php

namespace Tests\Unit;

use App\Models\Tenant\ShippingRequest;
use PHPUnit\Framework\TestCase;

/**
 * El caso que originó esto: el cliente llamaba con un número y el operador no
 * lo encontraba. Había TRES numeraciones para la misma caja y ninguna coincidía
 * con otra:
 *
 *   - el panel de Pedidos mostraba `orders.id`            → 000297
 *   - el rótulo impreso gritaba el `shipment_code`        → ENV-20260918-000280
 *   - la confirmación de la tienda online pintaba los 8
 *     primeros caracteres del UUID `external_id`          → A3F19C2B
 *
 * `orders.id` cuenta todos los pedidos y `shipping_requests.id` sólo los
 * envíos, así que la divergencia no es un desajuste puntual: es estructural y
 * crece. Estas pruebas fijan que la referencia que se ENSEÑA es siempre la del
 * pedido, y que el código ENV- sigue existiendo pero ya no es el titular.
 */
class ShipmentPublicRefTest extends TestCase
{
    private function envio(?int $orderId, string $code = 'ENV-20260918-000280'): ShippingRequest
    {
        $s = new ShippingRequest();
        $s->order_id      = $orderId;
        $s->shipment_code = $code;

        return $s;
    }

    /** El número que se enseña es el del PEDIDO, con el mismo relleno a 6 que pinta el panel. */
    public function test_la_referencia_publica_es_el_numero_de_pedido(): void
    {
        $s = $this->envio(297);

        $this->assertSame('000297', $s->orderRef());
        $this->assertSame('000297', $s->publicRef());
    }

    /**
     * El relleno tiene que ser IDÉNTICO al de OrderCollection (`str_pad(...,6)`):
     * si uno dijera «297» y el otro «000297», volveríamos al problema original
     * en su versión cosmética —el cliente lee un número y no lo reconoce en la
     * pantalla del operador—.
     */
    public function test_el_relleno_coincide_con_el_del_panel_de_pedidos(): void
    {
        $this->assertSame('000001',  $this->envio(1)->orderRef());
        $this->assertSame('012345',  $this->envio(12345)->orderRef());
        // Por encima de seis dígitos NO se trunca: str_pad sólo rellena.
        $this->assertSame('1234567', $this->envio(1234567)->orderRef());
    }

    /**
     * Las altas sueltas del formulario público antiguo no cuelgan de ningún
     * pedido. Ahí no se le puede mostrar al cliente una cadena vacía: cae al
     * código de envío, que es la única referencia que ese registro tiene.
     */
    public function test_sin_pedido_detras_cae_al_codigo_de_envio(): void
    {
        $s = $this->envio(null);

        $this->assertNull($s->orderRef());
        $this->assertSame('ENV-20260918-000280', $s->publicRef());
    }

    /**
     * El ENV- no desaparece: sigue siendo la clave interna —url de seguimiento,
     * código de barras, lotes de impresión—. Lo que cambia es su tamaño en el
     * papel, no su existencia.
     */
    public function test_el_codigo_de_envio_sobrevive_como_clave_interna(): void
    {
        $s = $this->envio(297);

        $this->assertSame('ENV-20260918-000280', $s->shipment_code);
        $this->assertNotSame($s->shipment_code, $s->publicRef());
    }

    /**
     * La raíz del desajuste, escrita como prueba: los dos contadores son
     * independientes, así que el pedido 297 puede perfectamente llevar el envío
     * 280. Si algún día alguien «arregla» esto igualando los ids, que falle
     * aquí y no en el mostrador.
     */
    public function test_las_dos_numeraciones_son_independientes(): void
    {
        $s = $this->envio(297, ShippingRequest::buildCode(280, '20260918'));

        $this->assertSame('ENV-20260918-000280', $s->shipment_code);
        $this->assertSame('000297', $s->publicRef());
    }
}
