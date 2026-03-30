<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\FeatureGate;
use Hyn\Tenancy\Environment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Mockery;

/**
 * Tests unitarios para FeatureGate.
 *
 * Verifica el comportamiento de control de acceso a features por plan:
 *   - fail-closed cuando no se puede resolver el plan
 *   - acceso correcto a features habilitados/deshabilitados
 *   - limites metered
 *   - cache por hostname
 */
class FeatureGateTest extends TestCase
{
    private function makeGateWithFeatures(?Collection $features): FeatureGate
    {
        $tenancy = Mockery::mock(Environment::class);

        // hostname mock para que cacheKey() retorne algo valido
        $hostname     = new \stdClass();
        $hostname->id = 999;
        $tenancy->shouldReceive('hostname')->andReturn($hostname);

        $gate = new FeatureGate($tenancy);

        // Inyectar features directamente en la cache para evitar queries
        if ($features !== null) {
            Cache::shouldReceive('remember')
                ->once()
                ->andReturn($features);
            Cache::shouldReceive('forget')->zeroOrMoreTimes();
        } else {
            // Simular que no se pudo resolver
            Cache::shouldReceive('remember')
                ->once()
                ->andReturn(null);
            Cache::shouldReceive('forget')->zeroOrMoreTimes();
        }

        return $gate;
    }

    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function returns_false_when_features_cannot_be_resolved()
    {
        // Arrange: simular que resolveFeatures retorna null (CLI sin tenant, error de BD)
        $gate = $this->makeGateWithFeatures(null);

        // Act & Assert: fail-closed — cuando no se puede verificar, denegar
        $this->assertFalse($gate->has('smart_stock'));
        $this->assertFalse($gate->has('ecommerce'));
        $this->assertFalse($gate->has('any_feature'));
    }

    /** @test */
    public function returns_true_for_enabled_feature()
    {
        // Arrange
        $features = collect([
            'smart_stock' => ['limit' => null, 'meta' => null],
            'ecommerce'   => ['limit' => null, 'meta' => null],
        ]);

        $gate = $this->makeGateWithFeatures($features);

        // Act & Assert
        $this->assertTrue($gate->has('smart_stock'), 'smart_stock esta en el plan');
        $this->assertTrue($gate->has('ecommerce'), 'ecommerce esta en el plan');
    }

    /** @test */
    public function returns_false_for_disabled_feature()
    {
        // Arrange: plan con solo smart_stock
        $features = collect([
            'smart_stock' => ['limit' => null, 'meta' => null],
        ]);

        $gate = $this->makeGateWithFeatures($features);

        // Act & Assert
        $this->assertFalse($gate->has('ecommerce'), 'ecommerce NO esta en el plan');
        $this->assertFalse($gate->has('logistic_module'), 'logistic_module NO esta en el plan');
    }

    /** @test */
    public function respects_feature_limits()
    {
        // Arrange
        $features = collect([
            'logistic_module' => ['limit' => 50, 'meta' => null],
            'smart_stock'     => ['limit' => null, 'meta' => null], // ilimitado
        ]);

        $gate = $this->makeGateWithFeatures($features);

        // Act & Assert
        $this->assertEquals(50, $gate->limit('logistic_module'), 'Limite debe ser 50');
        $this->assertNull($gate->limit('smart_stock'), 'null = ilimitado');
    }

    /** @test */
    public function limit_returns_zero_for_missing_feature()
    {
        // Arrange
        $features = collect([
            'smart_stock' => ['limit' => null, 'meta' => null],
        ]);

        $gate = $this->makeGateWithFeatures($features);

        // Act & Assert
        $this->assertEquals(0, $gate->limit('nonexistent_feature'), '0 = no incluido en el plan');
    }

    /** @test */
    public function limit_returns_zero_when_features_null()
    {
        // Arrange: no se pudo resolver
        $gate = $this->makeGateWithFeatures(null);

        // Act & Assert
        $this->assertEquals(0, $gate->limit('smart_stock'), '0 cuando no se puede resolver el plan');
    }

    /** @test */
    public function all_returns_feature_keys()
    {
        // Arrange
        $features = collect([
            'smart_stock' => ['limit' => null, 'meta' => null],
            'ecommerce'   => ['limit' => null, 'meta' => null],
            'logistic'    => ['limit' => 10, 'meta' => null],
        ]);

        $gate = $this->makeGateWithFeatures($features);

        // Act
        $keys = $gate->all();

        // Assert
        $this->assertCount(3, $keys);
        $this->assertContains('smart_stock', $keys);
        $this->assertContains('ecommerce', $keys);
        $this->assertContains('logistic', $keys);
    }

    /** @test */
    public function all_returns_empty_array_when_features_null()
    {
        // Arrange
        $gate = $this->makeGateWithFeatures(null);

        // Act & Assert
        $this->assertEquals([], $gate->all());
    }

    /** @test */
    public function returns_false_when_no_hostname_available()
    {
        // Arrange: tenancy sin hostname activo (ej: ejecucion CLI)
        $tenancy = Mockery::mock(Environment::class);
        $tenancy->shouldReceive('hostname')->andReturn(null);

        $gate = new FeatureGate($tenancy);

        // Act & Assert: sin hostname no se puede resolver, fail-closed
        $this->assertFalse($gate->has('smart_stock'));
        $this->assertEquals(0, $gate->limit('smart_stock'));
        $this->assertEquals([], $gate->all());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
