<?php

namespace Tests\Unit;

use App\Models\Tenant\Order;
use App\Services\Tenant\BillingDocumentResolver;
use Tests\TestCase;

/**
 * Con qué documento corresponde facturar un pedido.
 *
 * Sin base de datos: el resolutor solo lee columnas y JSON del propio pedido.
 *
 * Lo que se protege aquí son las dos reglas de SUNAT —a un RUC le corresponde
 * factura; una boleta sin identificar solo vale por debajo de S/ 700— y el
 * orden de precedencia entre las tres voces que opinan sobre el tipo: el
 * operador, el comprador y el documento del cliente.
 */
class BillingDocumentResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant' => config('database.connections.system')]);
    }

    private function resolver(): BillingDocumentResolver
    {
        return new BillingDocumentResolver();
    }

    private function pedido(array $atributos = []): Order
    {
        return new Order(array_merge([
            'total'    => 250.00,
            'items'    => [['item_id' => 1, 'quantity' => 1, 'unit_price' => 250]],
            'customer' => ['numero' => '44556677', 'apellidos_y_nombres_o_razon_social' => 'Ana Pérez'],
        ], $atributos));
    }

    // ── Regla 1: un RUC va en factura ─────────────────────────────────────

    /** @test */
    public function un_ruc_manda_sobre_todo_lo_demas()
    {
        // Incluso si el comprador pidió boleta en el checkout: emitirle una
        // boleta a un RUC le deja sin el comprobante que necesita.
        $r = $this->resolver()->resolve($this->pedido([
            'customer' => ['numero' => '20512345678', 'apellidos_y_nombres_o_razon_social' => 'Importaciones SAC'],
            'purchase' => ['codigo_tipo_documento' => '03'],
        ]));

        $this->assertSame(BillingDocumentResolver::FACTURA, $r['tipo']);
        $this->assertSame('ruc', $r['origen']);
        $this->assertTrue($r['puede_emitir']);
    }

    /** @test */
    public function con_ruc_se_respeta_la_nota_de_venta_si_el_operador_la_elige()
    {
        // La NV es interna y no va a SUNAT, así que la regla del RUC no aplica:
        // forzar factura aquí sería el sistema decidiendo por el operador.
        $r = $this->resolver()->resolve($this->pedido([
            'customer'                 => ['numero' => '20512345678'],
            'billing_document_type_id' => BillingDocumentResolver::NOTA_VENTA,
        ]));

        $this->assertSame(BillingDocumentResolver::NOTA_VENTA, $r['tipo']);
        $this->assertSame('operador', $r['origen']);
    }

    // ── Regla 2: el tope de la boleta anónima ─────────────────────────────

    /** @test */
    public function la_boleta_sin_documento_solo_vale_bajo_el_tope()
    {
        $bajo = $this->resolver()->resolve($this->pedido([
            'total' => 699, 'customer' => [],
        ]));
        $this->assertTrue($bajo['puede_emitir']);

        $alto = $this->resolver()->resolve($this->pedido([
            'total' => 701, 'customer' => [],
        ]));
        $this->assertFalse($alto['puede_emitir']);
        $this->assertStringContainsString('SUNAT', implode(' ', $alto['faltan']));
    }

    // ── Precedencia ───────────────────────────────────────────────────────

    /** @test */
    public function la_eleccion_del_operador_gana_a_la_del_comprador()
    {
        $r = $this->resolver()->resolve($this->pedido([
            'purchase'                 => ['codigo_tipo_documento' => '03'],
            'billing_document_type_id' => BillingDocumentResolver::NOTA_VENTA,
        ]));

        $this->assertSame(BillingDocumentResolver::NOTA_VENTA, $r['tipo']);
        $this->assertTrue($r['elegido_por_operador']);
    }

    /** @test */
    public function sin_operador_manda_lo_que_pidio_el_comprador()
    {
        $r = $this->resolver()->resolve($this->pedido([
            'purchase' => ['codigo_tipo_documento' => '80'],
        ]));

        $this->assertSame(BillingDocumentResolver::NOTA_VENTA, $r['tipo']);
        $this->assertSame('checkout', $r['origen']);
        $this->assertFalse($r['elegido_por_operador']);
    }

    /** @test */
    public function un_tipo_desconocido_del_checkout_se_ignora()
    {
        // Preferimos caer al criterio por defecto antes que proponer un tipo
        // que no sabemos emitir.
        $r = $this->resolver()->resolve($this->pedido([
            'purchase' => ['codigo_tipo_documento' => '07'],
        ]));

        $this->assertSame(BillingDocumentResolver::BOLETA, $r['tipo']);
        $this->assertSame('defecto', $r['origen']);
    }

    /** @test */
    public function sin_nada_declarado_se_propone_boleta()
    {
        $r = $this->resolver()->resolve($this->pedido());

        $this->assertSame(BillingDocumentResolver::BOLETA, $r['tipo']);
        $this->assertSame('defecto', $r['origen']);
    }

    // ── La corrección del operador pisa la foto del checkout ──────────────

    /** @test */
    public function los_datos_corregidos_ganan_a_los_del_checkout()
    {
        // Ese es el sentido de la corrección: el operador vio que el dato del
        // checkout estaba mal. `orders.customer` no se toca.
        $order = $this->pedido([
            'customer'         => ['numero' => '44556677', 'apellidos_y_nombres_o_razon_social' => 'Ana Pérez'],
            'billing_customer' => ['numero' => '20512345678', 'nombre' => 'Importaciones SAC'],
        ]);

        $r = $this->resolver()->resolve($order);

        $this->assertSame('20512345678', $r['documento']);
        $this->assertSame('Importaciones SAC', $r['nombre_cliente']);
        $this->assertSame(BillingDocumentResolver::FACTURA, $r['tipo']);

        // La foto del comprador sigue intacta.
        $this->assertSame('44556677', $order->customer['numero']);
    }

    /** @test */
    public function un_documento_con_guiones_o_espacios_se_normaliza()
    {
        $r = $this->resolver()->resolve($this->pedido([
            'customer' => ['numero' => '20-51234567-8'],
        ]));

        $this->assertSame('20512345678', $r['documento']);
        $this->assertSame(BillingDocumentResolver::FACTURA, $r['tipo']);
    }
}
