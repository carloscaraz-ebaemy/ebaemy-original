<?php

namespace Tests\Unit\Marketplace;

use App\Services\Marketplace\PaymentSummaryText;
use PHPUnit\Framework\TestCase;

class PaymentSummaryTextTest extends TestCase
{
    private array $pay = [
        'yape'     => ['phone' => '987654321', 'name' => 'Grupo Alasitas'],
        'plin'     => ['phone' => '912345678', 'name' => null],
        'transfer' => 'BCP soles 191-123-0-12 · CCI 002-191-000123',
        'cash'     => true,
        'notes'    => 'Envíanos la captura por WhatsApp',
    ];

    public function test_short_enumera_los_medios_en_una_frase(): void
    {
        $this->assertSame('Yape, Plin, transferencia o efectivo al recibir', PaymentSummaryText::short($this->pay));
        $this->assertSame('Yape', PaymentSummaryText::short(['yape' => ['phone' => '9']]));
        $this->assertNull(PaymentSummaryText::short(null));
        $this->assertNull(PaymentSummaryText::short(['notes' => 'solo nota']));
    }

    public function test_from_saca_el_bloque_pay_del_resumen_de_envio(): void
    {
        $this->assertSame($this->pay, PaymentSummaryText::from(['v' => 1, 'pay' => $this->pay]));
        $this->assertNull(PaymentSummaryText::from(['v' => 1]));
        $this->assertNull(PaymentSummaryText::from(null));
    }

    public function test_instructions_formatea_celular_y_titular(): void
    {
        $i = PaymentSummaryText::instructions($this->pay);
        $this->assertSame('Yape', $i[0]['method']);
        $this->assertSame('987 654 321 · Grupo Alasitas', $i[0]['detail']);
        $this->assertSame('912 345 678', $i[1]['detail']);
        $this->assertSame('Transferencia', $i[2]['method']);
        $this->assertSame('Efectivo', $i[3]['method']);
        $this->assertCount(4, $i);
    }

    public function test_plain_incluye_la_nota_al_final(): void
    {
        $lineas = explode("\n", PaymentSummaryText::plain($this->pay));
        $this->assertSame('Yape: 987 654 321 · Grupo Alasitas', $lineas[0]);
        $this->assertSame('Envíanos la captura por WhatsApp', end($lineas));
    }
}
