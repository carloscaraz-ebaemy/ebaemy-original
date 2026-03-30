<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Tenant\ItemWarehouse;
use App\Enums\StockMovementTypeEnum;
use Mockery;

/**
 * Tests unitarios para la logica de movimientos de stock.
 *
 * Verifican que StockMovementTypeEnum + ItemWarehouse::applyStockMovement()
 * produzcan los deltas correctos en stock_physical y stock_committed,
 * y que el stock nunca sea negativo.
 */
class StockMovementTest extends TestCase
{
    /**
     * Helper: crea un ItemWarehouse parcial (sin DB) con valores iniciales.
     */
    private function makeItemWarehouse(float $physical = 100, float $committed = 0): ItemWarehouse
    {
        $iw = Mockery::mock(ItemWarehouse::class)->makePartial()->shouldAllowMockingProtectedMethods();

        // Setear atributos internos como si vinieran de BD
        $iw->stock_physical  = $physical;
        $iw->stock_committed = $committed;
        $iw->stock           = $physical;
        $iw->item_id         = 1;
        $iw->warehouse_id    = 1;

        // Evitar que save() toque la BD
        $iw->shouldReceive('save')->andReturnTrue();

        return $iw;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // StockMovementTypeEnum: physicalDelta y committedDelta
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function sale_store_produces_negative_physical_delta_only()
    {
        // Arrange
        $type = StockMovementTypeEnum::SALE_STORE;
        $qty  = 5;

        // Act
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        // Assert
        $this->assertEquals(-5.0, $physicalDelta, 'SALE_STORE debe restar stock fisico');
        $this->assertEquals(0.0, $committedDelta, 'SALE_STORE no afecta stock comprometido');
    }

    /** @test */
    public function province_commit_produces_positive_committed_delta_only()
    {
        // Arrange
        $type = StockMovementTypeEnum::PROVINCE_COMMIT;
        $qty  = 3;

        // Act
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        // Assert
        $this->assertEquals(0.0, $physicalDelta, 'PROVINCE_COMMIT no afecta stock fisico');
        $this->assertEquals(3.0, $committedDelta, 'PROVINCE_COMMIT debe sumar stock comprometido');
    }

    /** @test */
    public function province_dispatch_decrements_both_physical_and_committed()
    {
        // Arrange
        $type = StockMovementTypeEnum::PROVINCE_DISPATCH;
        $qty  = 4;

        // Act
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        // Assert
        $this->assertEquals(-4.0, $physicalDelta, 'PROVINCE_DISPATCH debe restar stock fisico');
        $this->assertEquals(-4.0, $committedDelta, 'PROVINCE_DISPATCH debe restar stock comprometido');
    }

    /** @test */
    public function province_cancel_releases_committed_only()
    {
        // Arrange
        $type = StockMovementTypeEnum::PROVINCE_CANCEL;
        $qty  = 2;

        // Act
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        // Assert
        $this->assertEquals(0.0, $physicalDelta, 'PROVINCE_CANCEL no afecta stock fisico');
        $this->assertEquals(-2.0, $committedDelta, 'PROVINCE_CANCEL debe liberar stock comprometido');
    }

    /** @test */
    public function purchase_entry_increments_physical_only()
    {
        // Arrange
        $type = StockMovementTypeEnum::PURCHASE_ENTRY;
        $qty  = 50;

        // Act
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        // Assert
        $this->assertEquals(50.0, $physicalDelta, 'PURCHASE_ENTRY debe sumar stock fisico');
        $this->assertEquals(0.0, $committedDelta, 'PURCHASE_ENTRY no afecta stock comprometido');
    }

    /** @test */
    public function ecommerce_reserve_increments_committed_only()
    {
        // Arrange
        $type = StockMovementTypeEnum::ECOMMERCE_RESERVE;
        $qty  = 7;

        // Act
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        // Assert
        $this->assertEquals(0.0, $physicalDelta, 'ECOMMERCE_RESERVE no afecta stock fisico');
        $this->assertEquals(7.0, $committedDelta, 'ECOMMERCE_RESERVE debe sumar stock comprometido');
    }

    /** @test */
    public function ecommerce_cancel_releases_committed_only()
    {
        // Arrange
        $type = StockMovementTypeEnum::ECOMMERCE_CANCEL;
        $qty  = 7;

        // Act
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        // Assert
        $this->assertEquals(0.0, $physicalDelta, 'ECOMMERCE_CANCEL no afecta stock fisico');
        $this->assertEquals(-7.0, $committedDelta, 'ECOMMERCE_CANCEL debe liberar stock comprometido');
    }

    /** @test */
    public function dispatch_annul_restores_both_physical_and_committed()
    {
        // Arrange
        $type = StockMovementTypeEnum::DISPATCH_ANNUL;
        $qty  = 3;

        // Act
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        // Assert
        $this->assertEquals(3.0, $physicalDelta, 'DISPATCH_ANNUL debe restaurar stock fisico');
        $this->assertEquals(3.0, $committedDelta, 'DISPATCH_ANNUL debe restaurar stock comprometido');
    }

    /** @test */
    public function return_damaged_produces_no_stock_movement()
    {
        // Arrange
        $type = StockMovementTypeEnum::RETURN_DAMAGED;
        $qty  = 10;

        // Act
        $physicalDelta   = $type->physicalDelta($qty);
        $committedDelta  = $type->committedDelta($qty);

        // Assert
        $this->assertEquals(0.0, $physicalDelta, 'RETURN_DAMAGED no afecta stock fisico');
        $this->assertEquals(0.0, $committedDelta, 'RETURN_DAMAGED no afecta stock comprometido');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ItemWarehouse::applyStockMovement (integracion con el modelo)
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function sale_store_decrements_physical_stock()
    {
        // Arrange
        $iw = $this->makeItemWarehouse(physical: 100, committed: 10);

        // Act
        $iw->applyStockMovement(StockMovementTypeEnum::SALE_STORE, 5);

        // Assert
        $this->assertEquals(95.0, $iw->stock_physical, 'Stock fisico debe bajar de 100 a 95');
        $this->assertEquals(10.0, $iw->stock_committed, 'Stock comprometido no debe cambiar');
        $this->assertEquals(95.0, $iw->stock, 'stock legacy debe coincidir con stock_physical');
    }

    /** @test */
    public function province_commit_increments_committed_stock()
    {
        // Arrange
        $iw = $this->makeItemWarehouse(physical: 50, committed: 5);

        // Act
        $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_COMMIT, 10);

        // Assert
        $this->assertEquals(50.0, $iw->stock_physical, 'Stock fisico no debe cambiar');
        $this->assertEquals(15.0, $iw->stock_committed, 'Stock comprometido debe subir de 5 a 15');
    }

    /** @test */
    public function province_dispatch_decrements_both()
    {
        // Arrange
        $iw = $this->makeItemWarehouse(physical: 50, committed: 20);

        // Act
        $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_DISPATCH, 10);

        // Assert
        $this->assertEquals(40.0, $iw->stock_physical, 'Stock fisico debe bajar de 50 a 40');
        $this->assertEquals(10.0, $iw->stock_committed, 'Stock comprometido debe bajar de 20 a 10');
    }

    /** @test */
    public function province_cancel_releases_committed()
    {
        // Arrange
        $iw = $this->makeItemWarehouse(physical: 50, committed: 20);

        // Act
        $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_CANCEL, 8);

        // Assert
        $this->assertEquals(50.0, $iw->stock_physical, 'Stock fisico no debe cambiar');
        $this->assertEquals(12.0, $iw->stock_committed, 'Stock comprometido debe bajar de 20 a 12');
    }

    /** @test */
    public function stock_physical_never_goes_below_zero()
    {
        // Arrange: solo 5 unidades fisicas
        $iw = $this->makeItemWarehouse(physical: 5, committed: 0);

        // Act: intentar vender 10
        $iw->applyStockMovement(StockMovementTypeEnum::SALE_STORE, 10);

        // Assert: el modelo aplica max(0, ...) asi que nunca es negativo
        $this->assertEquals(0.0, $iw->stock_physical, 'Stock fisico debe ser 0, no negativo');
        $this->assertEquals(0.0, $iw->stock, 'stock legacy tampoco debe ser negativo');
    }

    /** @test */
    public function stock_committed_never_goes_below_zero()
    {
        // Arrange: solo 3 unidades comprometidas
        $iw = $this->makeItemWarehouse(physical: 50, committed: 3);

        // Act: cancelar 10 (mas de lo comprometido)
        $iw->applyStockMovement(StockMovementTypeEnum::PROVINCE_CANCEL, 10);

        // Assert
        $this->assertEquals(0.0, $iw->stock_committed, 'Stock comprometido debe ser 0, no negativo');
        $this->assertEquals(50.0, $iw->stock_physical, 'Stock fisico no debe cambiar');
    }

    /** @test */
    public function purchase_entry_increments_physical()
    {
        // Arrange
        $iw = $this->makeItemWarehouse(physical: 30, committed: 5);

        // Act
        $iw->applyStockMovement(StockMovementTypeEnum::PURCHASE_ENTRY, 100);

        // Assert
        $this->assertEquals(130.0, $iw->stock_physical, 'Stock fisico debe subir de 30 a 130');
        $this->assertEquals(5.0, $iw->stock_committed, 'Stock comprometido no debe cambiar');
    }

    /** @test */
    public function ecommerce_full_cycle_reserve_then_dispatch()
    {
        // Arrange: stock inicial
        $iw = $this->makeItemWarehouse(physical: 100, committed: 0);

        // Act: Paso 1 — Cliente hace checkout (reserva)
        $iw->applyStockMovement(StockMovementTypeEnum::ECOMMERCE_RESERVE, 5);

        // Assert intermedio
        $this->assertEquals(100.0, $iw->stock_physical, 'Reserva no toca stock fisico');
        $this->assertEquals(5.0, $iw->stock_committed, 'Reserva incrementa committed');

        // Act: Paso 2 — Se despacha
        $iw->applyStockMovement(StockMovementTypeEnum::ECOMMERCE_DISPATCH, 5);

        // Assert final
        $this->assertEquals(95.0, $iw->stock_physical, 'Despacho resta stock fisico');
        $this->assertEquals(0.0, $iw->stock_committed, 'Despacho libera committed');
    }

    /** @test */
    public function ecommerce_reserve_then_cancel_restores_committed()
    {
        // Arrange
        $iw = $this->makeItemWarehouse(physical: 100, committed: 0);

        // Act: reservar y luego cancelar
        $iw->applyStockMovement(StockMovementTypeEnum::ECOMMERCE_RESERVE, 5);
        $iw->applyStockMovement(StockMovementTypeEnum::ECOMMERCE_CANCEL, 5);

        // Assert: todo debe quedar como al inicio
        $this->assertEquals(100.0, $iw->stock_physical, 'Stock fisico no debe haber cambiado');
        $this->assertEquals(0.0, $iw->stock_committed, 'Committed debe volver a 0');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ItemWarehouse::getStockAvailableAttribute
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function stock_available_is_physical_minus_committed()
    {
        // Arrange
        $iw = new ItemWarehouse();
        $iw->stock_physical  = 50;
        $iw->stock_committed = 20;

        // Act & Assert
        $this->assertEquals(30.0, $iw->stock_available);
    }

    /** @test */
    public function stock_available_never_negative()
    {
        // Arrange: committed mayor que physical (anomalia)
        $iw = new ItemWarehouse();
        $iw->stock_physical  = 5;
        $iw->stock_committed = 10;

        // Act & Assert
        $this->assertEquals(0.0, $iw->stock_available, 'stock_available nunca debe ser negativo');
    }

    /** @test */
    public function has_available_stock_returns_true_when_sufficient()
    {
        $iw = new ItemWarehouse();
        $iw->stock_physical  = 50;
        $iw->stock_committed = 10;

        $this->assertTrue($iw->hasAvailableStock(40));
        $this->assertTrue($iw->hasAvailableStock(40.0));
    }

    /** @test */
    public function has_available_stock_returns_false_when_insufficient()
    {
        $iw = new ItemWarehouse();
        $iw->stock_physical  = 50;
        $iw->stock_committed = 10;

        $this->assertFalse($iw->hasAvailableStock(41));
        $this->assertFalse($iw->hasAvailableStock(100));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
