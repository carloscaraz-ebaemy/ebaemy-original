<?php

namespace Tests\Unit;

use App\Models\Tenant\Order;
use App\Models\Tenant\ShippingRequest;
use App\Services\Tenant\OrderShipmentLinker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Los checkouts (tienda online y marketplace) dejan el destino del comprador
 * como envio vigente del pedido. Integracion contra la BD LOCAL del tenant demo
 * (ebaemyoriginal_demo) dentro de una transaccion que SIEMPRE se revierte.
 */
class CheckoutCreatesShipmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $cfg = config('database.connections.system');
        $cfg['database'] = 'ebaemyoriginal_demo';
        config(['database.connections.tenant' => $cfg]);
        DB::purge('tenant');

        try {
            DB::connection('tenant')->getPdo();
            if (!DB::connection('tenant')->getSchemaBuilder()->hasTable('shipping_requests')) {
                $this->markTestSkipped('BD demo sin shipping_requests');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('BD demo no disponible: ' . $e->getMessage());
        }

        DB::connection('tenant')->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (DB::connection('tenant')->transactionLevel() > 0) {
            DB::connection('tenant')->rollBack();
        }
        parent::tearDown();
    }

    private function pedido(string $addr): Order
    {
        return Order::crearTolerandoEsquemaViejo([
            'external_id' => (string) \Illuminate\Support\Str::uuid(),
            'customer' => ['telefono' => '999111222', 'direccion' => $addr, 'apellidos_y_nombres_o_razon_social' => null],
            'shipping_address' => $addr,
            'items' => [['description' => 'Producto X', 'quantity' => 2]],
            'total' => 50, 'subtotal' => 50,
            'status_order_id' => 1, 'reference_payment' => 'efectivo',
        ]);
    }

    /** @test */
    public function tienda_online_lima_crea_envio_a_domicilio_con_ubigeo()
    {
        $o = $this->pedido('Av. Test 123');
        $s = app(OrderShipmentLinker::class)->ensureFromCheckout($o, [
            'pickup' => false, 'ubigeo' => '150117', 'address' => 'Av. Test 123',
            'name' => 'Ana Prueba', 'phone' => '999111222', 'document' => '46728787',
        ]);

        $this->assertNotNull($s);
        $this->assertSame($o->id, (int) $s->order_id);
        $this->assertSame('domicilio', $s->delivery_type);
        $this->assertSame('150117', $s->district_id);
        $this->assertSame('1501', $s->province_id);
        $this->assertSame('15', $s->department_id);
        $this->assertSame('Av. Test 123', $s->shipping_destination);
        $this->assertSame('Ana Prueba', $s->full_name);
        $this->assertSame('46728787', $s->dni);
        $this->assertSame($s->id, app(OrderShipmentLinker::class)->current($o)->id);

        // Idempotente
        $again = app(OrderShipmentLinker::class)->ensureFromCheckout($o, ['ubigeo' => '150117']);
        $this->assertSame($s->id, $again->id);
        $this->assertSame(1, ShippingRequest::where('order_id', $o->id)->count());
    }

    /** @test */
    public function tienda_online_recojo_crea_envio_tipo_tienda_sin_direccion()
    {
        $o = $this->pedido('Recojo en tienda');
        $s = app(OrderShipmentLinker::class)->ensureFromCheckout($o, [
            'pickup' => true, 'ubigeo' => '150101', 'address' => 'Recojo en tienda', 'phone' => '999111222',
        ]);

        $this->assertSame('tienda', $s->delivery_type);
        $this->assertNull($s->shipping_destination);
        // Sin nombre no se inventa uno: queda marcado y el panel lo reporta.
        $this->assertSame('Por completar', $s->full_name);
    }

    /** @test */
    public function marketplace_resuelve_el_distrito_por_nombres_y_provincia_queda_en_agencia_sin_agencia()
    {
        $o = $this->pedido('Jr. Lejos 45');
        $s = app(OrderShipmentLinker::class)->ensureFromCheckout($o, [
            'department' => 'Piura', 'province' => 'Talara', 'district' => 'Parinas',
            'address' => 'Jr. Lejos 45', 'reference' => 'Frente al parque',
            'name' => 'Luis Mkt', 'phone' => '988777666', 'document' => '12345678',
        ]);

        $this->assertNotNull($s);
        $this->assertSame('agencia', $s->delivery_type);
        $this->assertNotEmpty($s->district_id);
        $this->assertStringStartsWith('20', $s->district_id);
        $this->assertSame('Frente al parque', $s->reference);
        $this->assertNull($s->shipping_agency);
        // Lo que el checkout no pregunta lo reporta el panel, no se inventa.
        $this->assertContains('agencia de transporte', $s->missingLabelData());
    }

    /** @test */
    public function marketplace_lima_por_nombres_es_domicilio()
    {
        $o = $this->pedido('Calle Uno 1');
        $s = app(OrderShipmentLinker::class)->ensureFromCheckout($o, [
            'department' => 'Lima', 'province' => 'Lima', 'district' => 'Los Olivos',
            'address' => 'Calle Uno 1', 'name' => 'Rosa', 'phone' => '911222333',
        ]);

        $this->assertSame('domicilio', $s->delivery_type);
        $this->assertSame('150117', $s->district_id);
    }

    /** @test */
    public function si_crear_el_envio_falla_no_lanza_y_devuelve_null()
    {
        $o = new Order();   // sin id/persistir: current() consultara order_id null
        $o->forceFill(['customer' => [], 'items' => []]);
        DB::connection('tenant')->statement('SELECT 1');
        // Fuerza el fallo: tabla de distritos inexistente en la consulta de geo
        $linker = new class extends OrderShipmentLinker {
            public function ensure(Order $order, array $overrides = []): ShippingRequest
            {
                throw new \RuntimeException('boom');
            }
        };
        $this->assertNull($linker->ensureFromCheckout($o, ['ubigeo' => '150117']));
    }

    /** @test */
    public function backfill_dry_run_no_escribe_y_apply_crea_una_vez_y_es_idempotente()
    {
        $ch = \App\Models\Tenant\SalesChannel::where('code', 'ECOM')->first();
        if (!$ch) {
            $this->markTestSkipped('Sin canal ECOM');
        }
        $mk = function (string $addr, string $ubi, int $estado = 1) use ($ch) {
            return Order::crearTolerandoEsquemaViejo([
                'external_id' => (string) \Illuminate\Support\Str::uuid(),
                'customer' => ['telefono' => '900', 'direccion' => $addr, 'ubigeo' => $ubi, 'numero_documento' => '46728787'],
                'shipping_address' => $addr, 'items' => [], 'total' => 10, 'subtotal' => 10,
                'status_order_id' => $estado, 'reference_payment' => 'efectivo', 'channel_id' => $ch->id,
            ]);
        };
        $a = $mk('Av Uno 1', '150117');
        $b = $mk('Recojo en tienda', '150101');
        $c = $mk('Anulado 3', '150117', 5);   // no se toca

        $cmd  = new \App\Console\Commands\ShippingBackfillCheckout();
        $base = $cmd->backfillCurrentTenant(false);
        $this->assertSame(0, ShippingRequest::whereIn('order_id', [$a->id, $b->id, $c->id])->count());
        $this->assertGreaterThanOrEqual(2, $base['candidates']);
        $this->assertGreaterThanOrEqual(1, $base['pickup']);

        $r = $cmd->backfillCurrentTenant(true);
        $this->assertSame($base['candidates'], $r['created'] + $r['errors']);
        $this->assertSame(1, ShippingRequest::where('order_id', $a->id)->count());
        $this->assertSame('domicilio', ShippingRequest::where('order_id', $a->id)->value('delivery_type'));
        $this->assertSame('tienda', ShippingRequest::where('order_id', $b->id)->value('delivery_type'));
        $this->assertSame(0, ShippingRequest::where('order_id', $c->id)->count());

        $again = $cmd->backfillCurrentTenant(true);
        $this->assertSame(0, $again['candidates']);
        $this->assertSame(0, $again['created']);
    }
}
