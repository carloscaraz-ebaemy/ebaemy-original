<?php

namespace Tests\Unit;

use App\Exceptions\InvalidOrderTransitionException;
use App\Models\Tenant\Order;
use App\Models\Tenant\User;
use App\Policies\OrderPolicy;
use Mockery;
use Tests\TestCase;

/**
 * Tests unitarios para OrderPolicy::transitionTo().
 *
 * Cubren el contrato de transiciones de estado del pedido ecommerce
 * sin tocar la BD: usan Mockery para simular Order y User.
 */
class OrderPolicyTest extends TestCase
{
    private OrderPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new OrderPolicy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper: crea un Order con estado actual y payment_status dados.
     * Mockea getAttribute() para evitar los casts 'datetime' del modelo real,
     * que al acceder a `dispatched_at` requieren una conexión de BD configurada.
     */
    private function makeOrder(int $statusId, ?string $paymentStatus = null, ?string $dispatchedAt = null): Order
    {
        $order = Mockery::mock(Order::class)->makePartial();
        $order->shouldReceive('getAttribute')->with('status_order_id')->andReturn($statusId);
        $order->shouldReceive('getAttribute')->with('payment_status')->andReturn($paymentStatus);
        $order->shouldReceive('getAttribute')->with('dispatched_at')->andReturn($dispatchedAt);
        return $order;
    }

    /**
     * Helper: crea un User con tipo dado (admin, cashier, etc.).
     */
    private function makeUser(string $type = 'admin'): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->type = $type;
        return $user;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transiciones permitidas
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function same_status_is_allowed_as_noop()
    {
        $order = $this->makeOrder(2);
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $order, 2));
    }

    /** @test */
    public function pending_can_transition_to_verified_or_cancelled()
    {
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $this->makeOrder(1), 2));
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $this->makeOrder(1), 5));
    }

    /** @test */
    public function verified_can_transition_to_preparing_or_cancelled()
    {
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $this->makeOrder(2), 3));
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $this->makeOrder(2), 5));
    }

    /** @test */
    public function preparing_can_transition_to_dispatched_or_cancelled()
    {
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $this->makeOrder(3), 4));
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $this->makeOrder(3), 5));
    }

    /** @test */
    public function dispatched_can_transition_to_delivered_or_cancelled()
    {
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $this->makeOrder(4), 6));
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $this->makeOrder(4), 5));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Transiciones inválidas → InvalidOrderTransitionException
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function cannot_skip_from_pending_to_preparing()
    {
        $this->expectException(InvalidOrderTransitionException::class);
        $this->policy->transitionTo($this->makeUser(), $this->makeOrder(1), 3);
    }

    /** @test */
    public function cannot_transition_backwards()
    {
        $this->expectException(InvalidOrderTransitionException::class);
        $this->policy->transitionTo($this->makeUser(), $this->makeOrder(3), 1);
    }

    /** @test */
    public function cancelled_is_final_state()
    {
        $this->expectException(InvalidOrderTransitionException::class);
        $this->policy->transitionTo($this->makeUser(), $this->makeOrder(5), 3);
    }

    /** @test */
    public function delivered_is_final_state()
    {
        $this->expectException(InvalidOrderTransitionException::class);
        $this->policy->transitionTo($this->makeUser(), $this->makeOrder(6), 5);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Guard de payment_status para 1 → 2
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function verify_payment_allowed_when_payment_status_is_null_legacy()
    {
        $order = $this->makeOrder(1, null);
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $order, 2));
    }

    /** @test */
    public function verify_payment_allowed_when_payment_status_is_captured()
    {
        $order = $this->makeOrder(1, 'captured');
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $order, 2));
    }

    /** @test */
    public function verify_payment_allowed_when_payment_status_is_cash()
    {
        $order = $this->makeOrder(1, 'cash');
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $order, 2));
    }

    /** @test */
    public function verify_payment_blocked_when_culqi_is_pending_capture()
    {
        $this->expectException(InvalidOrderTransitionException::class);
        $this->expectExceptionMessageMatches('/pending_capture/');

        $order = $this->makeOrder(1, 'pending_capture');
        $this->policy->transitionTo($this->makeUser(), $order, 2);
    }

    /** @test */
    public function verify_payment_blocked_when_culqi_capture_failed()
    {
        $this->expectException(InvalidOrderTransitionException::class);
        $this->expectExceptionMessageMatches('/capture_failed/');

        $order = $this->makeOrder(1, 'capture_failed');
        $this->policy->transitionTo($this->makeUser(), $order, 2);
    }

    /** @test */
    public function payment_guard_only_applies_when_target_is_status_2()
    {
        // Aunque el pago esté pending_capture, cancelar (1→5) debe ser permitido
        $order = $this->makeOrder(1, 'pending_capture');
        $this->assertTrue($this->policy->transitionTo($this->makeUser(), $order, 5));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Regla por rol: cashier no puede cancelar pedido despachado
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function cashier_cannot_cancel_already_dispatched_order()
    {
        $order = $this->makeOrder(4, 'captured', '2026-04-20 12:00:00');
        $this->assertFalse($this->policy->transitionTo($this->makeUser('cashier'), $order, 5));
    }

    /** @test */
    public function admin_can_cancel_dispatched_order()
    {
        $order = $this->makeOrder(4, 'captured', '2026-04-20 12:00:00');
        $this->assertTrue($this->policy->transitionTo($this->makeUser('admin'), $order, 5));
    }

    /** @test */
    public function cashier_can_cancel_not_yet_dispatched_order()
    {
        $order = $this->makeOrder(2, 'captured', null);
        $this->assertTrue($this->policy->transitionTo($this->makeUser('cashier'), $order, 5));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Constantes expuestas: contrato estable
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function allowed_transitions_constant_matches_documented_flow()
    {
        $expected = [
            1 => [2, 5],
            2 => [3, 5],
            3 => [4, 5],
            4 => [6, 5],
            5 => [],
            6 => [],
        ];
        $this->assertSame($expected, OrderPolicy::ALLOWED_TRANSITIONS);
    }

    /** @test */
    public function blocked_payment_statuses_include_pending_and_failed()
    {
        $this->assertContains('pending_capture', OrderPolicy::BLOCKED_PAYMENT_STATUSES_FOR_VERIFY);
        $this->assertContains('capture_failed',  OrderPolicy::BLOCKED_PAYMENT_STATUSES_FOR_VERIFY);
    }
}
