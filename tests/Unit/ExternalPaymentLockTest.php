<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\OrderPaymentController;
use App\Models\Tenant\OrderPayment;
use ReflectionMethod;
use Tests\TestCase;

/**
 * El cobro que trajo una integración se muestra, no se toca.
 *
 * Nace de un fallo medido el 2026-09-16: un pedido de Saga entraba sin ningún
 * cobro registrado, así que su saldo era el total y el panel aceptaba un cobro
 * manual por el importe completo —y luego dejaba borrarlo—. Con el cobro del
 * canal sembrado, el saldo cierra la puerta; estos tests cubren la otra mitad:
 * que ese cobro no se pueda editar ni eliminar.
 */
class ExternalPaymentLockTest extends TestCase
{
    private function pago(?string $source): OrderPayment
    {
        $p = new OrderPayment();
        $p->source = $source;

        return $p;
    }

    private function motivo(OrderPayment $pago): ?string
    {
        $m = new ReflectionMethod(OrderPaymentController::class, 'paymentRecordLockReason');
        $m->setAccessible(true);

        return $m->invoke(new OrderPaymentController(), $pago);
    }

    public function test_un_cobro_manual_se_puede_editar_y_borrar()
    {
        $this->assertFalse($this->pago(OrderPayment::SOURCE_MANUAL)->esExterno());
        $this->assertNull($this->motivo($this->pago(OrderPayment::SOURCE_MANUAL)));
    }

    public function test_un_cobro_anterior_a_la_migracion_cuenta_como_manual()
    {
        // Las filas que ya existían no tienen origen y todas son manuales:
        // hasta ese día ninguna integración creaba cobros.
        $this->assertFalse($this->pago(null)->esExterno());
        $this->assertFalse($this->pago('')->esExterno());
        $this->assertNull($this->motivo($this->pago(null)));
    }

    public function test_el_cobro_de_saga_queda_bloqueado_y_dice_por_que()
    {
        $pago = $this->pago(OrderPayment::SOURCE_SAGA);

        $this->assertTrue($pago->esExterno());
        $this->assertSame('Saga Falabella', $pago->origenLabel());

        $motivo = $this->motivo($pago);

        $this->assertNotNull($motivo, 'El cobro del canal tiene que estar bloqueado');
        $this->assertStringContainsString('Saga Falabella', $motivo);
        $this->assertStringContainsString('portal del canal', $motivo, 'El motivo debe decir qué hacer');
    }

    public function test_un_canal_futuro_nace_protegido_sin_tocar_el_codigo()
    {
        // La comprobación es contra MANUAL, no contra una lista de canales: el
        // día que entre otro marketplace, su cobro ya está protegido.
        $pago = $this->pago('MERCADOLIBRE');

        $this->assertTrue($pago->esExterno());
        $this->assertNotNull($this->motivo($pago));
    }

    public function test_solo_se_bloquean_pagos_de_pedido()
    {
        // El trait lo comparten Pedidos y Notas de Pedido; un registro de otro
        // tipo no debe bloquearse por error.
        $this->assertNull($this->motivo(new OrderPayment()));
    }
}
