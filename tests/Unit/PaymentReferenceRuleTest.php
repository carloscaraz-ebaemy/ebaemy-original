<?php

namespace Tests\Unit;

use App\Services\Tenant\PaymentReferenceRule as Regla;
use PHPUnit\Framework\TestCase;

/**
 * El código de operación es obligatorio o no según el MÉTODO de pago.
 *
 * El caso que originó todo esto: cobrar en efectivo a CAJA GENERAL exigía un
 * número de operación que no existe, y el pago no se podía registrar.
 *
 * Se prueba sobre la descripción y no sobre el id porque el catálogo
 * `payment_method_types` lo edita cada tienda: los métodos que de verdad ve el
 * operador («Yape», «Depósito BCP», «Efectivo/Caja») no están en el catálogo
 * de fábrica.
 */
class PaymentReferenceRuleTest extends TestCase
{
    /** @dataProvider metodosOpcionales */
    public function test_metodos_sin_operacion_bancaria_no_exigen_codigo(string $descripcion): void
    {
        $this->assertFalse(Regla::descripcionRequiere($descripcion), $descripcion);
    }

    /** @dataProvider metodosObligatorios */
    public function test_operaciones_bancarias_exigen_codigo(string $descripcion): void
    {
        $this->assertTrue(Regla::descripcionRequiere($descripcion), $descripcion);
    }

    public function metodosOpcionales(): array
    {
        return array_map(fn ($d) => [$d], [
            'Efectivo',
            'EFECTIVO',
            'Caja General',
            'Contado',
            'Contado contraentrega',
            'Contra entrega',
            'Yape',
            'Plin',
            'Yape / Plin',
            'Tarjeta de crédito',
            'Tarjeta de débito',
            'Crédito',
            'A 30 días',
            'Factura a 30 días',
            // Mezclas: si el operador puede estar cobrando en mano, no se le
            // puede exigir un código que quizá no tenga.
            'Efectivo o depósito',
            // «cci» aparece dentro de palabras corrientes: como trozo suelto no
            // puede volver obligatorio un método que no lo es.
            'Pago fraccionado',
            'Yape / Transferencia',
            '',
            '   ',
        ]);
    }

    public function metodosObligatorios(): array
    {
        return array_map(fn ($d) => [$d], [
            'Transferencia',
            'transferencia',
            'Transferencia de fondos',
            'Transferencia interbancaria',
            'TRANSFERENCIA BCP',
            'Depósito',
            'Deposito',
            'DEPÓSITO EN CUENTA',
            'Depósito bancario',
            'Abono en cuenta',
            'CCI',
        ]);
    }

    /** Vacío, null y espacios en blanco son el MISMO hecho: no hay código. */
    public function test_los_espacios_en_blanco_no_son_un_codigo(): void
    {
        $this->assertNull(Regla::normalizar(null));
        $this->assertNull(Regla::normalizar(''));
        $this->assertNull(Regla::normalizar('   '));
        $this->assertNull(Regla::normalizar("\t\n "));

        $this->assertTrue(Regla::vacio(null));
        $this->assertTrue(Regla::vacio(''));
        $this->assertTrue(Regla::vacio('   '));
        $this->assertFalse(Regla::vacio(' 00123 '));
    }

    /** El código se guarda recortado, no con los espacios que se pegaron. */
    public function test_el_codigo_se_guarda_recortado(): void
    {
        $this->assertSame('000123456', Regla::normalizar('  000123456 '));
    }
}
