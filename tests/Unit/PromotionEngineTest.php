<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Tenant\DiscountRule;

/**
 * Tests unitarios para DiscountRule::calculateDiscount() y DiscountRule::matches().
 *
 * PromotionEngine depende de Eloquent queries (Coupon, DiscountRule scopes)
 * que necesitan BD, asi que aqui testeamos la logica pura del modelo DiscountRule
 * y los metodos que no requieren BD.
 *
 * El PromotionEngine::calculate() completo se testea en CheckoutFlowTest (feature).
 */
class PromotionEngineTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // DiscountRule::calculateDiscount
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function percentage_discount_applies_correct_amount()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->discount_type  = 'percentage';
        $rule->discount_value = 10; // 10%

        // Act
        $discount = $rule->calculateDiscount(100.00);

        // Assert
        $this->assertEquals(10.00, $discount, '10% de 100 debe ser 10');
    }

    /** @test */
    public function percentage_discount_rounds_to_two_decimals()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->discount_type  = 'percentage';
        $rule->discount_value = 15; // 15%

        // Act
        $discount = $rule->calculateDiscount(33.33);

        // Assert: 33.33 * 15 / 100 = 4.9995, redondeado = 5.00
        $this->assertEquals(5.00, $discount);
    }

    /** @test */
    public function fixed_amount_discount_applies_correctly()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->discount_type  = 'fixed';
        $rule->discount_value = 20; // S/20

        // Act
        $discount = $rule->calculateDiscount(100.00);

        // Assert
        $this->assertEquals(20.00, $discount, 'Descuento fijo de S/20 en carrito de S/100');
    }

    /** @test */
    public function fixed_discount_cannot_exceed_subtotal()
    {
        // Arrange: cupon de S/200 en carrito de S/100
        $rule = new DiscountRule();
        $rule->discount_type  = 'fixed';
        $rule->discount_value = 200;

        // Act
        $discount = $rule->calculateDiscount(100.00);

        // Assert: min(200, 100) = 100, el total nunca es negativo
        $this->assertEquals(100.00, $discount, 'Descuento no debe superar el subtotal');
    }

    /** @test */
    public function percentage_discount_cannot_exceed_subtotal()
    {
        // Arrange: 150% (escenario anomalo)
        $rule = new DiscountRule();
        $rule->discount_type  = 'percentage';
        $rule->discount_value = 150;

        // Act
        $discount = $rule->calculateDiscount(80.00);

        // Assert: 80 * 150% = 120, pero min(120, 80) = 80
        $this->assertEquals(80.00, $discount, 'Descuento porcentual no debe superar el subtotal');
    }

    /** @test */
    public function zero_subtotal_produces_zero_discount()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->discount_type  = 'percentage';
        $rule->discount_value = 10;

        // Act & Assert
        $this->assertEquals(0.00, $rule->calculateDiscount(0.00));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DiscountRule::matches — reglas de volumen
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function volume_rule_triggers_at_threshold()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->type         = 'volume';
        $rule->trigger_json = ['min_qty' => 3];

        $cart = [
            ['id' => 1, 'quantity' => 5, 'sale_unit_price' => 10],
        ];

        // Act & Assert
        $this->assertTrue(
            $rule->matches($cart, 50.0),
            'Regla de volumen con min_qty=3 debe activarse con qty=5'
        );
    }

    /** @test */
    public function volume_rule_does_not_trigger_below_threshold()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->type         = 'volume';
        $rule->trigger_json = ['min_qty' => 3];

        $cart = [
            ['id' => 1, 'quantity' => 2, 'sale_unit_price' => 10],
        ];

        // Act & Assert
        $this->assertFalse(
            $rule->matches($cart, 20.0),
            'Regla de volumen con min_qty=3 no debe activarse con qty=2'
        );
    }

    /** @test */
    public function volume_rule_for_specific_item_only_matches_that_item()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->type         = 'volume';
        $rule->trigger_json = ['min_qty' => 2, 'item_id' => 42];

        $cartWithWrongItem = [
            ['id' => 99, 'quantity' => 10, 'sale_unit_price' => 10],
        ];
        $cartWithCorrectItem = [
            ['id' => 42, 'quantity' => 3, 'sale_unit_price' => 10],
        ];

        // Act & Assert
        $this->assertFalse($rule->matches($cartWithWrongItem, 100.0), 'No debe activarse con otro item_id');
        $this->assertTrue($rule->matches($cartWithCorrectItem, 30.0), 'Debe activarse con el item_id correcto');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DiscountRule::matches — reglas automaticas (monto minimo)
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function auto_rule_triggers_at_min_amount()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->type         = 'auto';
        $rule->trigger_json = ['min_amount' => 100];

        // Act & Assert
        $this->assertTrue($rule->matches([], 100.0), 'Debe activarse con monto exacto');
        $this->assertTrue($rule->matches([], 150.0), 'Debe activarse por encima del minimo');
        $this->assertFalse($rule->matches([], 99.99), 'No debe activarse por debajo del minimo');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DiscountRule::matches — reglas de canal
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function channel_rule_matches_correct_channel_type()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->type         = 'channel';
        $rule->trigger_json = ['channel_type' => 'ecommerce'];

        // Act & Assert
        $this->assertTrue(
            $rule->matches([], 100.0, null, 'ecommerce'),
            'Debe activarse para canal ecommerce'
        );
        $this->assertFalse(
            $rule->matches([], 100.0, null, 'pos'),
            'No debe activarse para canal pos'
        );
    }

    /** @test */
    public function channel_rule_matches_specific_channel_id()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->type         = 'channel';
        $rule->trigger_json = ['channel_id' => 5];

        // Act & Assert
        $this->assertTrue($rule->matches([], 100.0, 5, null), 'Debe activarse para channel_id=5');
        $this->assertFalse($rule->matches([], 100.0, 3, null), 'No debe activarse para channel_id=3');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DiscountRule::matches — flash sale
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function flash_sale_always_matches_when_active()
    {
        // Arrange: flash_sale siempre retorna true (vigencia controlada por scope)
        $rule = new DiscountRule();
        $rule->type = 'flash_sale';

        // Act & Assert
        $this->assertTrue($rule->matches([], 10.0), 'Flash sale siempre aplica si esta activa');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DiscountRule::matches — bundle
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function bundle_rule_matches_when_set_item_in_cart()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->type          = 'bundle';
        $rule->apply_item_id = 10;
        $rule->trigger_json  = [];

        $cart = [
            ['id' => 10, 'quantity' => 1, 'is_set' => true],
        ];

        // Act & Assert
        $this->assertTrue($rule->matches($cart, 50.0), 'Bundle debe activarse con item set en carrito');
    }

    /** @test */
    public function bundle_rule_does_not_match_without_set_flag()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->type          = 'bundle';
        $rule->apply_item_id = 10;
        $rule->trigger_json  = [];

        $cart = [
            ['id' => 10, 'quantity' => 1, 'is_set' => false],
        ];

        // Act & Assert
        $this->assertFalse($rule->matches($cart, 50.0), 'Bundle no debe activarse sin is_set=true');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tipo desconocido
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function unknown_rule_type_does_not_match()
    {
        // Arrange
        $rule = new DiscountRule();
        $rule->type         = 'unknown_type';
        $rule->trigger_json = [];

        // Act & Assert
        $this->assertFalse($rule->matches([], 100.0), 'Tipo desconocido no debe activarse');
    }
}
