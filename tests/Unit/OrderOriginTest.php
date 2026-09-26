<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\OrderController;
use App\Models\Tenant\Order;
use App\Models\Tenant\SalesChannel;
use App\Services\Tenant\OrderOrigin;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * De dónde viene un pedido: los dos grupos del listado.
 *
 * Sin base de datos. La clasificación de canales se comprueba con modelos en
 * memoria, y el filtro con el SQL que produce `buildOrdersQuery()` — es donde
 * vivía el fallo que motivó esta clase: la regla estaba escrita dos veces (una
 * por fila y otra en SQL) y podían divergir sin que nada avisara.
 */
class OrderOriginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.tenant' => config('database.connections.system')]);
    }

    private function canal(string $code, string $type): SalesChannel
    {
        return new SalesChannel(['code' => $code, 'type' => $type]);
    }

    private function sqlFor(array $params): string
    {
        $controller = new OrderController();
        $method = new \ReflectionMethod($controller, 'buildOrdersQuery');
        $method->setAccessible(true);

        return $method->invoke($controller, Request::create('/', 'GET', $params), false, false)->toSql();
    }

    /** @test */
    public function los_portales_de_fuera_son_externos_lleven_o_no_el_prefijo()
    {
        // Como los crea la integración.
        $this->assertTrue(OrderOrigin::esCanalExterno($this->canal('MKP_FALABELLA', 'marketplace')));
        $this->assertTrue(OrderOrigin::esCanalExterno($this->canal('MKP_MERCADOLIBRE', 'marketplace')));

        // Y como los trae sembrados el tenant demo, SIN el prefijo. Este era el
        // agujero: `MKP_` no coincidía, el pedido de Falabella pasaba por propio
        // y se podía anular desde EBAEMY.
        $this->assertTrue(OrderOrigin::esCanalExterno($this->canal('SAGA', 'marketplace')));
        $this->assertTrue(OrderOrigin::esCanalExterno($this->canal('MELI', 'marketplace')));
    }

    /** @test */
    public function lo_nuestro_no_es_externo_aunque_se_llame_marketplace()
    {
        // MKP01 es el marketplace propio de ebaemy.com: nuestro de punta a punta.
        $this->assertFalse(OrderOrigin::esCanalExterno($this->canal('MKP01', 'marketplace')));

        $this->assertFalse(OrderOrigin::esCanalExterno($this->canal('ECOM', 'ecommerce')));
        $this->assertFalse(OrderOrigin::esCanalExterno($this->canal('ENV01', 'other')));
        $this->assertFalse(OrderOrigin::esCanalExterno($this->canal('POS01', 'pos')));

        // Sin canal no se afirma que venga de fuera.
        $this->assertFalse(OrderOrigin::esCanalExterno(null));
    }

    /** @test */
    public function el_pedido_hereda_el_grupo_de_su_canal()
    {
        $externo = new Order();
        $externo->setRelation('channel', $this->canal('MKP_FALABELLA', 'marketplace'));

        $propio = new Order();
        $propio->setRelation('channel', $this->canal('ENV01', 'other'));

        $this->assertSame(OrderOrigin::GRUPO_EXTERNO, OrderOrigin::grupoDe($externo));
        $this->assertSame(OrderOrigin::GRUPO_SISTEMA, OrderOrigin::grupoDe($propio));

        // Y la regla de negocio que cuelga de ahí.
        $this->assertFalse($externo->canBeCancelled());
        $this->assertTrue($propio->canBeCancelled());
    }

    /** @test */
    public function un_pedido_sin_canal_es_del_sistema_no_un_limbo()
    {
        // Son los 20 antiguos de alasitas: sin dato del que deducir el canal.
        // Tienen que salir en algún grupo, o el operador no los encuentra nunca.
        $huerfano = new Order();
        $huerfano->setRelation('channel', null);

        $this->assertSame(OrderOrigin::GRUPO_SISTEMA, OrderOrigin::grupoDe($huerfano));
    }

    /** @test */
    public function los_dos_grupos_filtran_y_son_complementarios()
    {
        $externo = $this->sqlFor(['order_source' => 'external']);
        $sistema = $this->sqlFor(['order_source' => 'system']);

        $this->assertStringContainsString('exists', strtolower($externo));
        // El complementario es el MISMO exists negado, no otra consulta: si
        // fueran dos reglas distintas, un pedido podría caer en los dos grupos
        // o en ninguno.
        $this->assertStringContainsString('not (exists', strtolower($sistema));

        // El guion bajo escapado: sin la barra, `MKP01` entraría en `MKP_%` y el
        // marketplace propio se contaría como pedido de fuera.
        $this->assertStringContainsString('MKP\_%', $this->bindingsFor(['order_source' => 'external']));
    }

    private function bindingsFor(array $params): string
    {
        $controller = new OrderController();
        $method = new \ReflectionMethod($controller, 'buildOrdersQuery');
        $method->setAccessible(true);

        $query = $method->invoke($controller, Request::create('/', 'GET', $params), false, false);

        return implode('|', array_map('strval', $query->getBindings()));
    }

    /** @test */
    public function un_origen_que_no_existe_se_rechaza_en_vez_de_devolver_todo()
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->sqlFor(['order_source' => 'lo_que_sea']);
    }

    /** @test */
    public function los_valores_heredados_siguen_aceptandose()
    {
        // Viajan en enlaces ya compartidos: rechazarlos sería un 422 para quien
        // tenga el enlace guardado.
        foreach (['all', 'saga', 'other', 'system', 'external'] as $source) {
            $this->assertIsString($this->sqlFor(['order_source' => $source]));
        }
    }

    /** @test */
    public function el_canal_none_aisla_los_pedidos_sin_origen()
    {
        $sql = strtolower($this->sqlFor(['channel_id' => 'none']));

        $this->assertStringContainsString('"channel_id" is null', str_replace('`', '"', $sql));
    }

    /** @test */
    public function un_canal_que_no_es_un_numero_se_rechaza()
    {
        // Antes entraba como id y MySQL lo casteaba a 0: cero filas con HTTP 200.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $this->sqlFor(['channel_id' => 'pepe']);
    }
}
