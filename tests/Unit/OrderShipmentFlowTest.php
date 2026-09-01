<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\OrderController;
use App\Models\Tenant\Order;
use App\Models\Tenant\SalesChannel;
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

    /**
     * El buscador de la tabla manda `column` + `value`. La búsqueda unificada
     * —la que alcanza código ENV, tracking, agencia, DNI y teléfono— sólo se
     * activa con la columna `search`; sin ese puente el parámetro `q` existía
     * en el backend pero ninguna pantalla lo usaba.
     *
     * @test
     */
    public function el_buscador_de_la_tabla_activa_la_busqueda_unificada()
    {
        $controller = new OrderController();
        $columnas   = $controller->columns();

        // Primera clave = criterio por defecto del DataTable.
        $this->assertSame('search', array_key_first($columnas));

        // Con `search` el texto NO se aplica como LIKE sobre una columna.
        $sql = $this->sqlFor(['column' => 'search', 'value' => 'SHL-998']);
        $this->assertStringContainsString('customer', $sql);

        // Con una columna concreta se mantiene el comportamiento de siempre.
        $this->assertStringContainsString(
            '"id" like',
            str_replace('`', '"', $this->sqlFor(['column' => 'id', 'value' => '125']))
        );
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

    /**
     * REGRESIÓN: `buildOrdersQuery` arrastra `latest()`, y cualquier consulta
     * agregada que agrupe hereda ese ORDER BY. Con GROUP BY eso revienta con
     * el error 1055 de MySQL (ONLY_FULL_GROUP_BY, el modo por defecto), y
     * `/orders/stats` devolvía 500 en producción.
     *
     * Se comprueba sobre el SQL: si alguien vuelve a agrupar sin `reorder()`,
     * este test lo caza antes de llegar al servidor.
     *
     * @test
     */
    public function las_consultas_agregadas_no_arrastran_el_order_by()
    {
        $controller = new OrderController();
        $method = new \ReflectionMethod($controller, 'buildOrdersQuery');
        $method->setAccessible(true);

        $base = $method->invoke($controller, Request::create('/', 'GET'), false, false);

        // Tal cual sale, la consulta SÍ ordena: por eso hay que quitar el orden
        // antes de agrupar.
        $this->assertStringContainsString('order by', $base->toSql());

        $agrupada = (clone $base)->reorder()
            ->selectRaw('channel_id, COUNT(*) as total')
            ->groupBy('channel_id');

        $sql = $agrupada->toSql();
        $this->assertStringContainsString('group by', $sql);
        $this->assertStringNotContainsString('order by', $sql);
    }

    /**
     * REGRESIÓN: las fechas comerciales llegaron en una migración posterior al
     * código. Entre el deploy y `tenancy:migrate` la columna no existe, y
     * filtrar por ella devolvía un 1054 que el operador veía como la pantalla
     * en blanco. Sin la columna, ningún pedido tiene esa fecha: la respuesta
     * honesta es "ninguno", no un error.
     *
     * Aquí no hay conexión, así que `orderHasColumn()` responde false para
     * todas — que es justo el escenario del tenant sin migrar.
     *
     * @test
     */
    public function filtrar_por_una_fecha_que_no_existe_no_revienta()
    {
        // Solo las tres de la migración nueva: las demás columnas existen desde
        // antes y guardarlas arriesgaría vaciar la vista por defecto.
        foreach (['paid', 'confirmed', 'cancelled'] as $tipo) {
            $this->assertStringContainsString(
                '1 = 0',
                $this->sqlFor(['date_type' => $tipo, 'range' => 'mes']),
                "date_type={$tipo} sin la columna debe devolver vacío, no un error"
            );
        }
    }

    /**
     * La relacion con el marketplace externo (Saga) necesita la misma guarda
     * que los envios: `Gestion de Pedidos` hace eager loading y `whereHas`
     * sobre `marketplace_orders`, y en un tenant sin esa tabla el listado
     * ENTERO se caia con un 1146 en vez de mostrar los pedidos que si tiene.
     *
     * Aqui no hay tenant activo, asi que `moduleInstalled()` responde false —
     * justo el escenario que se quiere cubrir.
     *
     * @test
     */
    public function el_listado_no_consulta_el_marketplace_externo_sin_la_tabla()
    {
        // Ni el listado normal ni el chip que depende de la boleta.
        foreach ([[], ['chip' => 'no_invoice'], ['order_source' => 'saga'],
                  ['order_source' => 'other']] as $params) {
            $this->assertStringNotContainsString(
                'marketplace_orders',
                $this->sqlFor($params),
                'params ' . json_encode($params) . ' no puede tocar marketplace_orders'
            );
        }

        // «saga» sin la tabla es "ninguno"; «otros» es "todos".
        $this->assertStringContainsString('1 = 0', $this->sqlFor(['order_source' => 'saga']));
        $this->assertStringContainsString('1 = 0', $this->sqlFor(['chip' => 'no_invoice']));
        $this->assertStringNotContainsString('1 = 0', $this->sqlFor(['order_source' => 'other']));
    }

    /**
     * El `DataTable` manda SIEMPRE `warehouse_id`, y su valor por defecto es la
     * cadena 'all' (el selector solo se dibuja en /inventory). Al tratarla como
     * un id, MySQL la casteaba a 0 contra una columna entera y el listado
     * devolvia CERO filas con HTTP 200: la pantalla de Pedidos aparecia vacia
     * sin un solo error en el log ni en consola.
     *
     * @test
     */
    public function el_almacen_solo_filtra_cuando_es_un_id_de_verdad()
    {
        foreach (['all', '', 'todos'] as $noEsUnId) {
            $this->assertStringNotContainsString(
                'warehouse_id',
                $this->sqlFor(['warehouse_id' => $noEsUnId]),
                "«{$noEsUnId}» no es un almacen: no puede convertirse en un WHERE"
            );
        }

        $this->assertStringContainsString(
            'warehouse_id',
            $this->sqlFor(['warehouse_id' => '3']),
            'un id real si debe filtrar por almacen'
        );
    }

    /**
     * El codigo del canal de un marketplace externo lo calculan DOS sitios: el
     * alta en vivo (`marketplacePlatformChannel`) y la migracion de backfill.
     * Si divergen, cada uno crea un canal distinto para la misma tienda y el
     * reporte de ventas queda partido en dos — por eso la formula vive en una
     * sola funcion pura y por eso se fija aqui.
     *
     * @test
     */
    public function el_codigo_de_canal_de_una_plataforma_es_estable()
    {
        $casos = [
            'falabella'      => 'MKP_FALABELLA',
            'FALABELLA'      => 'MKP_FALABELLA',
            '  Falabella  '  => 'MKP_FALABELLA',
            'mercado-libre'  => 'MKP_MERCADOLIBRE',
            'tik tok'        => 'MKP_TIKTOK',
        ];

        foreach ($casos as $plataforma => $esperado) {
            $this->assertSame(
                $esperado,
                SalesChannel::platformCode($plataforma),
                "«{$plataforma}» debe resolver siempre al mismo codigo"
            );
        }

        // La columna `sales_channels.code` es varchar(20) y UNIQUE: pasarse
        // reventaria el alta del canal justo al recibir el primer pedido.
        $this->assertLessThanOrEqual(
            20,
            strlen(SalesChannel::platformCode('una-plataforma-con-nombre-larguisimo')),
            'el codigo no puede exceder el varchar(20) de la columna'
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
