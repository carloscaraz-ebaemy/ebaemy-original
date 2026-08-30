<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\OrderController;
use App\Models\Tenant\Order;
use App\Models\Tenant\ShippingRequest;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Invariantes de la unificación Pedidos ↔ Envíos.
 *
 * Dos bloques:
 *   1. Reglas puras del flujo logístico (modalidad, prioridad, estados).
 *   2. SQL que produce el listado unificado, comprobado con `toSql()` — sin
 *      ejecutar nada, así que no hace falta base de datos.
 *
 * En este contexto `ShippingRequest::moduleInstalled()` devuelve false (no hay
 * tenant activo), de modo que el bloque 2 cubre justamente el tenant que nunca
 * corrió `shipping:install`, que es donde vivían los fallos más caros.
 */
class OrderShipmentFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant' => config('database.connections.system')]);
    }

    private function sqlFor(array $params): string
    {
        $controller = new OrderController();
        $method = new \ReflectionMethod($controller, 'buildOrdersQuery');
        $method->setAccessible(true);

        // Sin relaciones: solo interesa el WHERE que se construye.
        return $method->invoke($controller, Request::create('/', 'GET', $params), false, true)->toSql();
    }

    // ── 1. Reglas del flujo logístico ──────────────────────────────────

    /** @test */
    public function cada_modalidad_tiene_su_propio_recorrido_de_estados()
    {
        $lima      = ShippingRequest::statusOrderFor(ShippingRequest::DELIVERY_DOMICILIO);
        $provincia = ShippingRequest::statusOrderFor(ShippingRequest::DELIVERY_AGENCIA);
        $tienda    = ShippingRequest::statusOrderFor(ShippingRequest::DELIVERY_TIENDA);

        // El recojo en tienda no se rotula ni se despacha.
        $this->assertNotContains(ShippingRequest::STATUS_IMPRESO, $tienda);
        $this->assertNotContains(ShippingRequest::STATUS_DESPACHADO, $tienda);
        $this->assertContains(ShippingRequest::STATUS_LISTO_RECOJO, $tienda);

        // Provincia termina en la agencia; Lima, con el motorizado.
        $this->assertContains(ShippingRequest::STATUS_EN_AGENCIA, $provincia);
        $this->assertNotContains(ShippingRequest::STATUS_EN_AGENCIA, $lima);
        $this->assertContains(ShippingRequest::STATUS_EN_CAMINO, $lima);

        // Los tres acaban entregados.
        foreach ([$lima, $provincia, $tienda] as $flujo) {
            $this->assertSame(ShippingRequest::STATUS_ENTREGADO, end($flujo));
        }
    }

    /** @test */
    public function una_modalidad_desconocida_cae_en_el_flujo_de_provincia()
    {
        $this->assertSame(
            ShippingRequest::statusOrderFor(ShippingRequest::DELIVERY_AGENCIA),
            ShippingRequest::statusOrderFor('inventada')
        );
    }

    /** @test */
    public function la_prioridad_se_deriva_de_la_modalidad()
    {
        $this->assertSame(1, ShippingRequest::priorityFor(ShippingRequest::DELIVERY_DOMICILIO));
        $this->assertSame(2, ShippingRequest::priorityFor(ShippingRequest::DELIVERY_TIENDA));
        $this->assertSame(3, ShippingRequest::priorityFor(ShippingRequest::DELIVERY_AGENCIA));
        $this->assertSame(3, ShippingRequest::priorityFor(null));
    }

    /**
     * Los valores de BD se conservaron a propósito: hay envíos vivos, rótulos y
     * QR apoyados en ellos. Renombrarlos obligaría a migrar datos.
     *
     * @test
     */
    public function los_valores_de_modalidad_en_base_no_cambian()
    {
        $this->assertSame('domicilio', ShippingRequest::DELIVERY_DOMICILIO);
        $this->assertSame('agencia',   ShippingRequest::DELIVERY_AGENCIA);
        $this->assertSame('tienda',    ShippingRequest::DELIVERY_TIENDA);
        $this->assertSame(ShippingRequest::DELIVERY_DOMICILIO, ShippingRequest::DELIVERY_LIMA);
        $this->assertSame(ShippingRequest::DELIVERY_AGENCIA,   ShippingRequest::DELIVERY_PROVINCIA);
    }

    /** @test */
    public function el_reloj_de_atencion_se_detiene_cuando_el_paquete_sale()
    {
        foreach ([ShippingRequest::STATUS_EN_AGENCIA, ShippingRequest::STATUS_EN_CAMINO,
                  ShippingRequest::STATUS_ENTREGADO, ShippingRequest::STATUS_ANULADO,
                  ShippingRequest::STATUS_LISTO_RECOJO] as $estado) {
            $this->assertContains($estado, ShippingRequest::CLOSED_STATUSES);
        }

        // Los que siguen siendo trabajo de la tienda NO paran el reloj.
        foreach ([ShippingRequest::STATUS_RECIBIDO, ShippingRequest::STATUS_PREPARANDO,
                  ShippingRequest::STATUS_IMPRESO, ShippingRequest::STATUS_EMBALANDO] as $estado) {
            $this->assertNotContains($estado, ShippingRequest::CLOSED_STATUSES);
        }
    }

    /** @test */
    public function los_dias_habiles_ignoran_fines_de_semana_y_feriados()
    {
        $viernes = \Illuminate\Support\Carbon::parse('2026-08-28');
        $lunes   = \Illuminate\Support\Carbon::parse('2026-08-31');

        // Sábado y domingo no cuentan; el 30 de agosto es feriado (domingo).
        $this->assertSame(1, ShippingRequest::businessDaysBetween($viernes, $lunes));
        $this->assertSame(0, ShippingRequest::businessDaysBetween($viernes, $viernes));
    }

    /** @test */
    public function el_codigo_del_envio_es_legible_y_ordenable()
    {
        $this->assertSame('ENV-20260830-000125', ShippingRequest::buildCode(125, '20260830'));
    }

    // ── 2. SQL del listado unificado (tenant SIN el módulo) ────────────

    /** @test */
    public function sin_el_modulo_de_envios_la_guarda_esta_activa()
    {
        $this->assertFalse(ShippingRequest::moduleInstalled());
    }

    /**
     * REGRESIÓN: `aging` resolvía la configuración con `currentOrNull()`, que
     * sin módulo devuelve null, y el filtro se saltaba entero — "vencidos"
     * contestaba con TODOS los pedidos, lo contrario de lo que se preguntó.
     *
     * @test
     */
    public function el_filtro_de_antiguedad_no_se_ignora_sin_modulo()
    {
        foreach (['urgentes', 'vencidos'] as $nivel) {
            $this->assertStringContainsString(
                '1 = 0',
                $this->sqlFor(['aging' => $nivel]),
                "aging={$nivel} debe devolver vacío, no todos los pedidos"
            );
        }
    }

    /** @test */
    public function todos_los_filtros_logisticos_devuelven_vacio_sin_modulo()
    {
        $filtros = [
            ['delivery_type' => 'agencia'],
            ['shipping_status' => 'recibido'],
            ['batch_id' => 3],
            ['priority' => 1],
            ['with_shipping_guide' => 1],
            ['without_shipping_guide' => 1],
            ['date_type' => 'printed', 'date_from' => '2026-01-01'],
        ];

        foreach ($filtros as $params) {
            $this->assertStringContainsString(
                '1 = 0',
                $this->sqlFor($params),
                'filtro ' . key($params) . ' debe devolver vacío'
            );
        }
    }

    /**
     * "Sin envío" es lo contrario: sin módulo NINGÚN pedido tiene envío, así
     * que la respuesta correcta es todos, no ninguno.
     *
     * @test
     */
    public function sin_envio_devuelve_todos_los_pedidos_cuando_no_hay_modulo()
    {
        foreach ([['without_shipment' => 1], ['chip' => 'sin_envio']] as $params) {
            $sql = $this->sqlFor($params);
            $this->assertStringNotContainsString('1 = 0', $sql);
            $this->assertStringNotContainsString('shipping_requests', $sql);
        }
    }

    /** @test */
    public function los_filtros_comerciales_siguen_funcionando_sin_modulo()
    {
        $this->assertStringContainsString('status_order_id', $this->sqlFor(['status_order_id' => 2]));
        $this->assertStringContainsString('payment_status',  $this->sqlFor(['payment_status' => 'paid']));
        $this->assertStringContainsString('channel_id',      $this->sqlFor(['channel_id' => 7]));
    }

    /** @test */
    public function ningun_chip_consulta_la_tabla_de_envios_sin_modulo()
    {
        $chips = [
            'por_confirmar', 'por_preparar', 'por_imprimir', 'por_embalar',
            'por_despachar', 'en_transito', 'listos_recojo', 'entregados',
            'anulados', 'sin_envio', 'todispatch', 'shipped', 'canceled', 'no_invoice',
        ];

        foreach ($chips as $chip) {
            $this->assertStringNotContainsString(
                'shipping_requests',
                $this->sqlFor(['chip' => $chip]),
                "el chip «{$chip}» no debe tocar shipping_requests"
            );
        }
    }

    /** @test */
    public function la_busqueda_no_toca_la_tabla_de_envios_sin_modulo()
    {
        $sql = $this->sqlFor(['q' => '73964630']);

        $this->assertStringNotContainsString('shipping_requests', $sql);
        $this->assertStringContainsString('customer', $sql);
    }

    /** @test */
    public function el_rango_rapido_de_fechas_se_traduce_a_la_columna_correcta()
    {
        $this->assertStringContainsString('created_at', $this->sqlFor(['range' => 'hoy']));
        $this->assertStringContainsString('delivered_at', $this->sqlFor([
            'date_type' => 'delivered', 'range' => 'mes',
        ]));
        // `updated_at` no es una fecha de negocio y no debe ser filtrable.
        $this->assertStringContainsString('created_at', $this->sqlFor([
            'date_type' => 'updated', 'range' => 'hoy',
        ]));
    }

    /** @test */
    public function un_chip_desconocido_no_filtra_nada()
    {
        $this->assertSame(
            $this->sqlFor([]),
            $this->sqlFor(['chip' => 'inventado'])
        );
    }

    /** @test */
    public function el_pedido_expone_las_fechas_de_negocio()
    {
        $casts = (new Order())->getCasts();

        foreach (['paid_at', 'confirmed_at', 'cancelled_at',
                  'prepared_at', 'dispatched_at', 'delivered_at'] as $campo) {
            $this->assertSame('datetime', $casts[$campo], "«{$campo}» debe castearse a fecha");
        }
    }
}
