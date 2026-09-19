<?php

namespace Tests\Unit;

use App\Models\Tenant\ShippingRequest;
use PHPUnit\Framework\TestCase;

/**
 * El selector de agencia permite escribir una que no esté en la lista —hay
 * transportistas locales que no salen en ninguna—, y sin normalizar «Shalom»,
 * «shalom » y «SHALOM» entraban como TRES agencias distintas: los filtros del
 * panel y los lotes de impresión las contaban por separado, así que el mismo
 * transportista aparecía partido en tres columnas.
 *
 * Se prueba el catálogo real (`ShippingRequest::AGENCIES`), no uno de mentira:
 * lo que hay que garantizar es que las doce que el sistema ofrece se escriban
 * siempre igual.
 */
class ShippingAgencyNormalizationTest extends TestCase
{
    /** @dataProvider variantesDeShalom */
    public function test_las_variantes_de_escritura_caen_en_el_nombre_del_catalogo(string $entrada): void
    {
        $this->assertSame('Shalom', ShippingRequest::normalizeAgency($entrada));
    }

    public static function variantesDeShalom(): array
    {
        return [
            'tal cual'            => ['Shalom'],
            'minúsculas'          => ['shalom'],
            'mayúsculas'          => ['SHALOM'],
            'con espacios'        => ['  Shalom  '],
            'espacios internos'   => ['Sha lom'],
        ];
    }

    public function test_las_tildes_no_parten_una_agencia_en_dos(): void
    {
        // «Movil Tours» sin tilde es lo que teclea la mitad de la gente.
        $this->assertSame('Móvil Tours', ShippingRequest::normalizeAgency('movil tours'));
        $this->assertSame('Móvil Tours', ShippingRequest::normalizeAgency('MOVIL TOURS'));
        $this->assertSame('Transportes Línea', ShippingRequest::normalizeAgency('transportes linea'));
    }

    /**
     * Una agencia que no está en el catálogo se respeta: el objetivo es unificar
     * la escritura, no impedir que exista un transportista local.
     */
    public function test_una_agencia_desconocida_se_conserva_solo_recortada(): void
    {
        $this->assertSame(
            'Transportes El Chasqui',
            ShippingRequest::normalizeAgency('  Transportes   El Chasqui  ')
        );
    }

    public function test_sin_agencia_devuelve_null_y_no_cadena_vacia(): void
    {
        // Null es «no hay agencia»; '' se guardaría como una agencia sin nombre
        // y el rótulo de provincia saldría con el hueco en blanco.
        $this->assertNull(ShippingRequest::normalizeAgency(null));
        $this->assertNull(ShippingRequest::normalizeAgency('   '));
    }
}
