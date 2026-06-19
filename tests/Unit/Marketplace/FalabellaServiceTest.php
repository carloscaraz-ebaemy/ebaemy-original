<?php

namespace Tests\Unit\Marketplace;

use Tests\TestCase;
use App\Services\Marketplace\FalabellaService;
use Illuminate\Support\Facades\Http;

/**
 * Cubre los casos de la capa de API de Saga Falabella que NO requieren BD:
 * conexión, autenticación, API caída, rate limit, respuesta inválida y
 * normalización del catálogo / árbol de categorías. Usa Http::fake().
 */
class FalabellaServiceTest extends TestCase
{
    private const BASE = 'sellercenter-api.falabella.com*';

    private function service(array $creds = ['user_id' => 'u@e.com', 'api_key' => 'secret']): FalabellaService
    {
        return FalabellaService::fromCredentials($creds);
    }

    public function test_conexion_exitosa_cuando_saga_responde_success()
    {
        Http::fake([self::BASE => Http::response(['SuccessResponse' => ['Body' => ['Products' => ['Product' => []]]]], 200)]);

        $r = $this->service()->testConnection();

        $this->assertTrue($r['success']);
    }

    public function test_error_de_autenticacion_firma_invalida()
    {
        Http::fake([self::BASE => Http::response([
            'ErrorResponse' => ['Head' => ['ErrorCode' => '007', 'ErrorMessage' => 'E007: Signature mismatch']],
        ], 200)]);

        $r = $this->service()->testConnection();

        $this->assertFalse($r['success']);
        $this->assertStringContainsString('Signature', $r['message']);
    }

    public function test_sin_credenciales_no_intenta_conectar()
    {
        Http::fake();

        $r = $this->service([])->testConnection();

        $this->assertFalse($r['success']);
        Http::assertNothingSent();
    }

    public function test_api_caida_lanza_excepcion()
    {
        Http::fake([self::BASE => Http::response('Service Unavailable', 503)]);

        $this->expectException(\Exception::class);
        $this->service()->getProducts();
    }

    public function test_rate_limit_lanza_excepcion()
    {
        Http::fake([self::BASE => Http::response(['ErrorResponse' => ['Head' => ['ErrorMessage' => 'Throttled']]], 429)]);

        $this->expectException(\Exception::class);
        $this->service()->getProducts();
    }

    public function test_respuesta_vacia_se_normaliza_a_lista()
    {
        Http::fake([self::BASE => Http::response(['SuccessResponse' => ['Body' => ['Products' => '']]], 200)]);

        $this->assertSame([], $this->service()->getProducts());
    }

    public function test_un_solo_producto_se_normaliza_a_lista()
    {
        Http::fake([self::BASE => Http::response(['SuccessResponse' => ['Body' => ['Products' => ['Product' => [
            'SellerSku' => 'ABC', 'Name' => 'X',
        ]]]]], 200)]);

        $prods = $this->service()->getProducts();

        $this->assertCount(1, $prods);
        $this->assertSame('ABC', $prods[0]['SellerSku']);
    }

    public function test_arbol_de_categorias_se_aplana_y_marca_hojas()
    {
        Http::fake([self::BASE => Http::response(['SuccessResponse' => ['Body' => ['Categories' => ['Category' => [
            ['Name' => 'Moda', 'CategoryId' => '1', 'Children' => ['Category' => [
                ['Name' => 'Mujer', 'CategoryId' => '2', 'Children' => ['Category' => [
                    ['Name' => 'Vestidos', 'CategoryId' => '3'],
                ]]],
            ]]],
        ]]]]], 200)]);

        $tree = $this->service()->getCategoryTree();

        $this->assertCount(3, $tree);
        $leaf = collect($tree)->firstWhere('id', '3');
        $this->assertTrue($leaf['is_leaf']);
        $this->assertSame('Moda › Mujer › Vestidos', $leaf['path']);
        $this->assertFalse(collect($tree)->firstWhere('id', '1')['is_leaf']);
    }
}
