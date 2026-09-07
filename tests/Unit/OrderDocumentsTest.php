<?php

namespace Tests\Unit;

use App\Models\Tenant\Document;
use App\Models\Tenant\Order;
use App\Models\Tenant\SaleNote;
use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\OrderDocuments;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Reglas de documentos del pedido: qué corresponde y qué está bloqueado.
 *
 * Sin base de datos. `OrderDocuments` lee EXCLUSIVAMENTE de relaciones ya
 * cargadas —esa es su regla de diseño, para no meter 20 consultas por página—
 * así que se le pueden inyectar con `setRelation()` y las reglas quedan
 * comprobables en aislamiento, que es donde importan.
 *
 * Lo que se protege aquí son las dos guardas anti-duplicado que NO existían en
 * ninguna parte del sistema (boleta y factura a la vez; comprobante ya emitido
 * por un camino distinto al que mira la pantalla) y la regla de que el pedido
 * espejo de un encargo logístico no factura nada.
 */
class OrderDocumentsTest extends TestCase
{
    /**
     * Igual que en `OrderShipmentLinkerTest`: `hyn` solo registra la conexión
     * `tenant` con un tenant activo, y sin ella no se puede ni construir una
     * relación. Estos tests no ejecutan ninguna consulta.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant' => config('database.connections.system')]);
    }

    // ── Fábricas ──────────────────────────────────────────────────────────

    /** Pedido de venta con contenido: una línea y un importe. */
    private function pedidoDeVenta(array $extra = []): Order
    {
        $order = new Order(array_merge([
            'total'           => 250.00,
            'items'           => [['item_id' => 1, 'quantity' => 1, 'unit_price' => 250]],
            'customer'        => ['numero' => '44556677', 'apellidos_y_nombres_o_razon_social' => 'Cliente'],
            'status_order_id' => 2,
        ], $extra));

        // Sin documento alguno cargado: es un pedido recién nacido.
        $order->setRelation('sale_note', null);

        return $order;
    }

    /** El espejo de un encargo logístico: 0 líneas, total 0. */
    private function pedidoEspejo(): Order
    {
        $order = new Order([
            'total'           => 0,
            'items'           => [],
            'customer'        => ['numero' => '44556677'],
            'status_order_id' => 2,
        ]);
        $order->setRelation('sale_note', null);

        return $order;
    }

    private function comprobante(string $tipoSunat, string $estado = '05'): Document
    {
        $d = new Document([
            'document_type_id' => $tipoSunat,
            'state_type_id'    => $estado,
            'series'           => $tipoSunat === '01' ? 'F001' : 'B001',
            'number'           => 42,
        ]);
        $d->external_id = 'ext-' . $tipoSunat;

        return $d;
    }

    /** Cuelga los comprobantes del pedido por el camino de la nota de venta. */
    private function conNotaDeVenta(Order $order, array $documentos = [], string $estado = '01'): Order
    {
        $nv = new SaleNote(['series' => 'NV01', 'number' => 7, 'state_type_id' => $estado]);
        $nv->setRelation('documents', new Collection($documentos));
        $order->setRelation('sale_note', $nv);

        return $order;
    }

    private function docs(Order $order): array
    {
        return OrderDocuments::for($order)->toArray();
    }

    // ── El pedido espejo no factura ───────────────────────────────────────

    /** @test */
    public function el_espejo_de_un_encargo_no_ofrece_documentos_comerciales()
    {
        $docs = $this->docs($this->pedidoEspejo());

        // `null`, no una casilla vacía: pintarla invitaría al operador a
        // intentar facturar S/ 0.00 y recibir un rechazo que no puede leer.
        $this->assertNull($docs[OrderDocuments::NOTA_VENTA]);
        $this->assertNull($docs[OrderDocuments::BOLETA]);
        $this->assertNull($docs[OrderDocuments::FACTURA]);
    }

    /** @test */
    public function un_pedido_con_lineas_e_importe_si_los_ofrece()
    {
        $docs = $this->docs($this->pedidoDeVenta());

        $this->assertNotNull($docs[OrderDocuments::NOTA_VENTA]);
        $this->assertFalse($docs[OrderDocuments::NOTA_VENTA]['existe']);
        $this->assertNull($docs[OrderDocuments::NOTA_VENTA]['bloqueo']);
    }

    /** @test */
    public function un_pedido_con_importe_pero_sin_lineas_no_es_facturable()
    {
        // Un pedido mal cargado a mano: importe sin detalle. No hay nada que
        // detallar en el comprobante.
        $docs = $this->docs($this->pedidoDeVenta(['items' => []]));

        $this->assertNull($docs[OrderDocuments::BOLETA]);
    }

    // ── Guarda: boleta y factura son excluyentes ──────────────────────────

    /** @test */
    public function con_boleta_emitida_la_factura_queda_bloqueada()
    {
        $order = $this->conNotaDeVenta(
            $this->pedidoDeVenta(['customer' => ['numero' => '20512345678']]),
            [$this->comprobante('03')]
        );

        $docs = $this->docs($order);

        $this->assertTrue($docs[OrderDocuments::BOLETA]['existe']);
        $this->assertSame('B001-42', $docs[OrderDocuments::BOLETA]['numero']);

        $this->assertFalse($docs[OrderDocuments::FACTURA]['existe']);
        $this->assertStringContainsString('B001-42', $docs[OrderDocuments::FACTURA]['bloqueo']);
        $this->assertStringContainsString('nota de crédito', $docs[OrderDocuments::FACTURA]['bloqueo']);
    }

    /** @test */
    public function con_factura_emitida_la_boleta_queda_bloqueada()
    {
        $order = $this->conNotaDeVenta($this->pedidoDeVenta(), [$this->comprobante('01')]);

        $docs = $this->docs($order);

        $this->assertTrue($docs[OrderDocuments::FACTURA]['existe']);
        $this->assertStringContainsString('F001-42', $docs[OrderDocuments::BOLETA]['bloqueo']);
    }

    /** @test */
    public function un_comprobante_rechazado_no_bloquea_la_reemision()
    {
        // Estado 09 = Rechazado. No documenta nada, así que dejar al pedido sin
        // salida por su culpa sería un callejón: hay que poder volver a emitir.
        $order = $this->conNotaDeVenta($this->pedidoDeVenta(), [$this->comprobante('03', '09')]);

        $docs = $this->docs($order);

        $this->assertFalse($docs[OrderDocuments::BOLETA]['existe']);
        $this->assertNull($docs[OrderDocuments::BOLETA]['bloqueo']);
    }

    // ── Guarda: el comprobante enlazado por otro camino ───────────────────

    /** @test */
    public function encuentra_el_comprobante_enlazado_en_el_propio_pedido()
    {
        // `orders.document_external_id`: el camino del flujo antiguo del panel.
        // Mirar solo la nota de venta daba «sin comprobante» para un pedido ya
        // facturado — y ofrecía emitir el segundo.
        $order = $this->pedidoDeVenta();
        $order->setRelation('document', $this->comprobante('03'));

        $docs = $this->docs($order);

        $this->assertTrue($docs[OrderDocuments::BOLETA]['existe']);
        $this->assertSame('Aceptado', $docs[OrderDocuments::BOLETA]['estado_label']);
    }

    /** @test */
    public function un_comprobante_emitido_en_el_portal_del_canal_bloquea_la_emision()
    {
        // Saga permite emitir desde su portal: el pedido queda con
        // `invoice_uploaded_at` y SIN Document local. Sin esta guarda los tres
        // caminos dan «no existe» y la pantalla ofrecería emitir el segundo
        // comprobante de una venta ya documentada.
        $order = $this->pedidoDeVenta();
        $mo = new \App\Models\Tenant\MarketplaceOrder(['invoice_uploaded_at' => now()]);
        $order->setRelation('marketplaceOrder', $mo);

        $docs = $this->docs($order);

        $this->assertFalse($docs[OrderDocuments::BOLETA]['existe']);
        $this->assertStringContainsString('fuera de EBAEMY', $docs[OrderDocuments::BOLETA]['bloqueo']);
        $this->assertStringContainsString('fuera de EBAEMY', $docs[OrderDocuments::FACTURA]['bloqueo']);
    }

    // ── Factura: la exige SUNAT, no el sistema ────────────────────────────

    /** @test */
    public function la_factura_exige_un_ruc_de_once_digitos()
    {
        $conDni = $this->docs($this->pedidoDeVenta());
        $this->assertStringContainsString('RUC', $conDni[OrderDocuments::FACTURA]['bloqueo']);

        $conRuc = $this->docs($this->pedidoDeVenta([
            'customer' => ['numero' => '20512345678'],
        ]));
        $this->assertNull($conRuc[OrderDocuments::FACTURA]['bloqueo']);
    }

    /** @test */
    public function la_boleta_no_exige_ruc()
    {
        $docs = $this->docs($this->pedidoDeVenta());

        $this->assertNull($docs[OrderDocuments::BOLETA]['bloqueo']);
    }

    // ── Pedido anulado ────────────────────────────────────────────────────

    /** @test */
    public function un_pedido_anulado_no_emite_nada()
    {
        $docs = $this->docs($this->pedidoDeVenta(['status_order_id' => 5]));

        foreach ([OrderDocuments::NOTA_VENTA, OrderDocuments::BOLETA, OrderDocuments::FACTURA] as $tipo) {
            $this->assertStringContainsString('anulado', $docs[$tipo]['bloqueo'], $tipo);
        }
    }

    // ── Guía de remisión ──────────────────────────────────────────────────

    /** @test */
    public function sin_envio_no_hay_guia()
    {
        $order = $this->pedidoDeVenta();
        $order->setRelation('shipment', null);

        $this->assertNull($this->docs($order)[OrderDocuments::GUIA]);
    }

    /** @test */
    public function el_recojo_en_tienda_no_lleva_guia()
    {
        // El paquete no viaja a un destinatario externo: lo retira el propio
        // cliente. La regla ya vive en `ShippingRequest::canGenerateDispatch()`
        // y aquí se comprueba que se está consultando, no reimplementando.
        $envio = new ShippingRequest([
            'delivery_type' => ShippingRequest::DELIVERY_TIENDA,
            'full_name'     => 'Cliente',
            'phone'         => '999999999',
        ]);
        $order = $this->pedidoDeVenta();
        $order->setRelation('shipment', $envio);

        $this->assertNull($this->docs($order)[OrderDocuments::GUIA]);
    }

    /** @test */
    public function la_guia_avisa_de_los_datos_que_le_faltan_al_envio()
    {
        $envio = new ShippingRequest([
            'delivery_type' => ShippingRequest::DELIVERY_AGENCIA,
            'full_name'     => '',
            'phone'         => '',
        ]);
        $order = $this->pedidoDeVenta();
        $order->setRelation('shipment', $envio);

        $guia = $this->docs($order)[OrderDocuments::GUIA];

        $this->assertNotNull($guia);
        $this->assertFalse($guia['existe']);
        $this->assertStringContainsString('destinatario', $guia['bloqueo']);
    }
}
