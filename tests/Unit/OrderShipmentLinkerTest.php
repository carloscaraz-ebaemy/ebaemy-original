<?php

namespace Tests\Unit;

use App\Models\Tenant\Order;
use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\OrderShipmentLinker;
use Tests\TestCase;

/**
 * Prellenado del envío a partir del pedido (unificación Pedidos ↔ Envíos).
 *
 * Sin base de datos: se usa un `Order` en memoria. Las relaciones que toca
 * `prefill()` (channel, marketplaceOrder) devuelven null sin consultar cuando
 * la clave es null, así que el prellenado es comprobable en aislamiento — que
 * es justo donde viven las reglas que importan.
 */
class OrderShipmentLinkerTest extends TestCase
{
    /**
     * `hyn` solo registra la conexión `tenant` cuando hay un tenant activo, y
     * sin ella no se puede ni construir una relación. Se apunta a la misma
     * configuración que `system`: basta con que EXISTA, porque estos tests no
     * ejecutan ninguna consulta.
     *
     * No se usa sqlite en memoria a propósito: el PHP del proyecto no trae
     * `pdo_sqlite`, así que el test no correría en esta máquina ni en el server.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant' => config('database.connections.system')]);
    }

    private function linker(): OrderShipmentLinker
    {
        return new OrderShipmentLinker();
    }

    /**
     * Pedido en memoria y SIN id: con clave, `marketplaceOrder` (hasOne) haría
     * una consulta real. Con la clave nula Laravel la corta y el prellenado
     * queda comprobable sin base de datos.
     */
    private function order(array $customer = [], array $items = [], array $attrs = []): Order
    {
        $order = new Order();
        $order->forceFill(array_merge([
            'customer' => $customer,
            'items'    => $items,
            'total'    => 100,
        ], $attrs));

        return $order;
    }

    /** @test */
    public function hereda_los_datos_que_el_pedido_ya_conoce()
    {
        $data = $this->linker()->prefill($this->order([
            'apellidos_y_nombres_o_razon_social' => 'Maria Quispe',
            'numero_documento' => '73964630',
            'telefono'         => '978995189',
            'direccion'        => 'Av. Siempre Viva 742',
        ]));

        $this->assertSame('Maria Quispe', $data['full_name']);
        $this->assertSame('73964630', $data['dni']);
        $this->assertSame('978995189', $data['phone']);
        $this->assertSame('Av. Siempre Viva 742', $data['shipping_destination']);
    }

    /**
     * REGRESIÓN: el prellenado ponía "Cliente" cuando el pedido no traía
     * nombre, y ese placeholder terminaba impreso en el rótulo de la agencia.
     * Un campo vacío obliga al operador a escribirlo; uno relleno no se nota.
     *
     * @test
     */
    public function sin_nombre_en_el_pedido_devuelve_null_y_no_un_placeholder()
    {
        $data = $this->linker()->prefill($this->order([
            'apellidos_y_nombres_o_razon_social' => null,
            'numero_documento' => '46728787',
            'telefono'         => '986896387',
        ]));

        $this->assertNull($data['full_name']);
        $this->assertNotSame('Cliente', $data['full_name']);
        // El resto del prellenado sigue funcionando: solo falta el nombre.
        $this->assertSame('46728787', $data['dni']);
    }

    /** @test */
    public function distingue_ruc_de_dni_por_la_cantidad_de_digitos()
    {
        $dni = $this->linker()->prefill($this->order(['numero_documento' => '73964630']));
        $ruc = $this->linker()->prefill($this->order(['numero_documento' => '20123456789']));

        $this->assertSame('dni', $dni['document_type']);
        $this->assertSame('ruc', $ruc['document_type']);
    }

    /**
     * El ERP guarda "Marketplace" como marcador cuando el canal no entrega la
     * dirección real. Copiarlo al rótulo sería despachar a una dirección que
     * no existe.
     *
     * @test
     */
    public function descarta_el_marcador_marketplace_como_direccion()
    {
        $data = $this->linker()->prefill($this->order(['direccion' => 'Marketplace']));

        $this->assertNull($data['shipping_destination']);
    }

    /** @test */
    public function resume_el_contenido_del_paquete_con_las_cantidades()
    {
        $data = $this->linker()->prefill($this->order([], [
            ['description' => 'Polo algodon', 'quantity' => 2],
            ['description' => 'Gorra',        'quantity' => 1],
        ]));

        $this->assertSame('2 x Polo algodon · Gorra', $data['package_content']);
    }

    /** @test */
    public function el_contenido_del_paquete_no_desborda_la_columna()
    {
        $items = array_fill(0, 40, ['description' => str_repeat('Producto ', 5), 'quantity' => 1]);

        $data = $this->linker()->prefill($this->order([], $items));

        $this->assertLessThanOrEqual(255, mb_strlen($data['package_content']));
    }

    /** @test */
    public function sin_items_no_inventa_contenido()
    {
        $this->assertNull($this->linker()->prefill($this->order())['package_content']);
    }

    /** @test */
    public function el_codigo_del_pedido_es_el_que_ve_el_operador()
    {
        $order = $this->order();
        $order->forceFill(['id' => 125]);   // orderCode solo lee el id, no consulta

        $this->assertSame('000125', $this->linker()->orderCode($order));
    }

    /**
     * El prellenado NO adivina modalidad, agencia ni ubigeo: inventarlos
     * produciría rótulos mal dirigidos. Esos campos los pide el formulario.
     *
     * @test
     */
    public function no_adivina_la_modalidad_ni_el_destino_logistico()
    {
        $data = $this->linker()->prefill($this->order([
            'apellidos_y_nombres_o_razon_social' => 'Maria Quispe',
        ]));

        foreach (['delivery_type', 'shipping_agency', 'department_id', 'province_id', 'district_id'] as $campo) {
            $this->assertArrayNotHasKey($campo, $data, "prefill no debe fijar «{$campo}»");
        }
    }

    /** @test */
    public function los_ids_de_estado_del_pedido_estan_congelados()
    {
        // Cambiarlos rompería la sincronización envío → pedido y los chips.
        $this->assertSame(4, OrderShipmentLinker::ORDER_SHIPPED);
        $this->assertSame(5, OrderShipmentLinker::ORDER_CANCELLED);
        $this->assertSame(6, OrderShipmentLinker::ORDER_DELIVERED);
    }

    /** @test */
    public function el_pedido_conoce_su_detalle_logistico()
    {
        $order = new Order();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasOne::class,
            $order->shipment()
        );
        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\HasMany::class,
            $order->shipments()
        );
        $this->assertSame('order_id', $order->shipments()->getForeignKeyName());
    }

    /** @test */
    public function el_envio_conoce_su_pedido()
    {
        $shipment = new ShippingRequest();

        $this->assertInstanceOf(
            \Illuminate\Database\Eloquent\Relations\BelongsTo::class,
            $shipment->order()
        );
        $this->assertSame('order_id', $shipment->order()->getForeignKeyName());
    }
}
