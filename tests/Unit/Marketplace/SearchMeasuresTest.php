<?php

namespace Tests\Unit\Marketplace;

use App\Services\System\SearchMeasures;
use PHPUnit\Framework\TestCase;

/**
 * Medidas del buscador: «2.40», «2,40 m», «240cm», «2metros» y «2 m» tienen que
 * quedar en la misma forma, en el índice y en la consulta. Caso real
 * (2026-11-18): «arbol de navidad 2.40» y «arbol 2metros» no casaban con
 * «240 cm» ni con «2 m».
 */
class SearchMeasuresTest extends TestCase
{
    public function test_metros_y_centimetros_quedan_en_centimetros_pegados()
    {
        $this->assertSame('arbol 240cm', SearchMeasures::normalize('arbol 2.40 m'));
        $this->assertSame('arbol 240cm', SearchMeasures::normalize('arbol 2,40 m'));
        $this->assertSame('arbol 240cm', SearchMeasures::normalize('arbol 2.40m'));
        $this->assertSame('arbol 240cm', SearchMeasures::normalize('arbol 240 cm'));
        $this->assertSame('arbol 240cm', SearchMeasures::normalize('arbol 240cm'));
        $this->assertSame('arbol 200cm', SearchMeasures::normalize('arbol 2metros'));
        $this->assertSame('arbol 200cm', SearchMeasures::normalize('arbol 2 m'));
        $this->assertSame('Arbol 200cm', SearchMeasures::normalize('Arbol 2 Metros'));
        $this->assertSame('pino 210cm', SearchMeasures::normalize('pino 2.1 m'));
        $this->assertSame('cortina 150cm de alto', SearchMeasures::normalize('cortina 1.5mts de alto'));
        $this->assertSame('cinta 30cm', SearchMeasures::normalize('cinta 30 centímetros'));
    }

    public function test_es_idempotente()
    {
        $una = SearchMeasures::normalize('pino 2,40 m y 90 cm');
        $this->assertSame('pino 240cm y 90cm', $una);
        $this->assertSame($una, SearchMeasures::normalize($una));
    }

    public function test_no_toca_lo_que_no_es_una_longitud_entera()
    {
        // Centímetros con decimales: no hay forma entera.
        $this->assertSame('boton 1.5 cm', SearchMeasures::normalize('boton 1.5 cm'));
        // Superficie, multiplicaciones y unidades pegadas a otra palabra.
        $this->assertSame('piso 2 m2', SearchMeasures::normalize('piso 2 m2'));
        $this->assertSame('mesa 2x2 m', SearchMeasures::normalize('mesa 2x2 m'));
        $this->assertSame('pack x24 m', SearchMeasures::normalize('pack x24 m'));
        // Número sin unidad y palabras que empiezan como una unidad.
        $this->assertSame('arbol 2.40', SearchMeasures::normalize('arbol 2.40'));
        $this->assertSame('2 mesas', SearchMeasures::normalize('2 mesas'));
        $this->assertSame('polo talla m', SearchMeasures::normalize('polo talla m'));
        // Miles con coma: no es un decimal.
        $this->assertSame('cable 1,200 m', SearchMeasures::normalize('cable 1,200 m'));
    }

    public function test_un_decimal_suelto_se_prueba_tambien_como_metros()
    {
        $this->assertSame(['240cm'], SearchMeasures::alternatives('2.40'));
        $this->assertSame(['240cm'], SearchMeasures::alternatives('2,4'));
        $this->assertSame(['180cm'], SearchMeasures::alternatives('1.80'));
        // Fuera de lo que mide un producto en metros, o sin decimales: nada.
        $this->assertSame([], SearchMeasures::alternatives('0.1'));
        $this->assertSame([], SearchMeasures::alternatives('12.50'));
        $this->assertSame([], SearchMeasures::alternatives('240'));
        $this->assertSame([], SearchMeasures::alternatives('polo'));
    }

    public function test_la_forma_canonica_se_prueba_tambien_separada()
    {
        // Para índices aún no resincronizados, que guardan «240 cm».
        $this->assertSame(['240 cm'], SearchMeasures::alternatives('240cm'));
    }
}
