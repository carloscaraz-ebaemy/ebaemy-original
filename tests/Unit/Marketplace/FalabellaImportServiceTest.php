<?php

namespace Tests\Unit\Marketplace;

use App\Services\Marketplace\FalabellaImportService;
use App\Services\Marketplace\SagaImageImporter;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Cubre las DECISIONES del importador de catálogo de Saga que no requieren BD:
 * qué unidad de negocio manda, qué precio gana, qué estado bloquea la
 * publicación y qué imágenes se traen.
 *
 * Todas nacen de fallos reales que la importación tenía en silencio (ver los
 * commits b42ebab7 y 6b8454dd); el objetivo es que no vuelvan sin que un test
 * lo diga.
 */
class FalabellaImportServiceTest extends TestCase
{
    /**
     * Invoca un método protegido del servicio.
     *
     * Sin constructor a propósito: el constructor real resuelve el almacén del
     * tenant contra la BD, y estas decisiones (qué unidad de negocio, qué
     * precio, qué estado) son lógica pura que no depende de nada de eso.
     */
    private function invoke(string $method, array $args)
    {
        $service = (new ReflectionClass(FalabellaImportService::class))->newInstanceWithoutConstructor();

        $m = new ReflectionMethod(FalabellaImportService::class, $method);
        $m->setAccessible(true);

        return $m->invokeArgs($service, $args);
    }

    private function bu(array $over = []): array
    {
        return array_merge([
            'OperatorCode' => 'fape',
            'Price'        => '100.00',
            'SpecialPrice' => '0',
            'Stock'        => '5',
            'Status'       => 'active',
        ], $over);
    }

    // ── Unidad de negocio ──────────────────────────────────────

    public function test_elige_la_unidad_de_negocio_de_falabella_y_no_la_primera()
    {
        $producto = ['BusinessUnits' => ['BusinessUnit' => [
            $this->bu(['OperatorCode' => 'sodi', 'Price' => '999.00', 'Stock' => '99']),
            $this->bu(['OperatorCode' => 'fape', 'Price' => '100.00', 'Stock' => '5']),
        ]]];

        $bu = $this->invoke('resolveBusinessUnit', [$producto]);

        $this->assertSame('100.00', $bu['Price'], 'Debe tomar el precio de Falabella, no el de Sodimac');
        $this->assertSame('5', $bu['Stock']);
    }

    public function test_una_sola_unidad_de_negocio_viene_como_objeto_suelto()
    {
        $producto = ['BusinessUnits' => ['BusinessUnit' => $this->bu(['Price' => '42.00'])]];

        $bu = $this->invoke('resolveBusinessUnit', [$producto]);

        $this->assertSame('42.00', $bu['Price']);
    }

    public function test_sin_unidad_de_falabella_cae_en_la_primera()
    {
        $producto = ['BusinessUnits' => ['BusinessUnit' => [
            $this->bu(['OperatorCode' => 'sodi', 'Price' => '70.00']),
            $this->bu(['OperatorCode' => 'tott', 'Price' => '80.00']),
        ]]];

        $bu = $this->invoke('resolveBusinessUnit', [$producto]);

        $this->assertSame('70.00', $bu['Price']);
    }

    // ── Precios ────────────────────────────────────────────────

    public function test_oferta_vigente_manda_y_el_regular_queda_tachado()
    {
        [$price, $compareAt, $until, $from] = $this->invoke('resolvePrices', [
            $this->bu(['Price' => '100.00', 'SpecialPrice' => '80.00', 'SpecialFromDate' => '2020-01-01', 'SpecialToDate' => '2999-01-01']),
        ]);

        $this->assertSame(80.0, $price);
        $this->assertSame(100.0, $compareAt);
        $this->assertSame('2999-01-01', $until);
        $this->assertSame('2020-01-01', $from);
    }

    public function test_oferta_caducada_no_se_aplica_y_no_deja_precio_tachado()
    {
        [$price, $compareAt] = $this->invoke('resolvePrices', [
            $this->bu(['Price' => '100.00', 'SpecialPrice' => '80.00', 'SpecialToDate' => '2020-01-01']),
        ]);

        $this->assertSame(100.0, $price);
        $this->assertNull($compareAt);
    }

    public function test_oferta_mas_cara_que_el_regular_se_ignora()
    {
        [$price, $compareAt] = $this->invoke('resolvePrices', [
            $this->bu(['Price' => '50.00', 'SpecialPrice' => '90.00']),
        ]);

        $this->assertSame(50.0, $price);
        $this->assertNull($compareAt);
    }

    public function test_sin_ningun_precio_devuelve_cero_para_que_el_importador_lo_rechace()
    {
        [$price] = $this->invoke('resolvePrices', [$this->bu(['Price' => '0', 'SpecialPrice' => '0'])]);

        $this->assertSame(0.0, $price, 'Precio 0 es la señal de "Saga no mandó precio"');
    }

    // ── Estado en Saga ─────────────────────────────────────────

    /** @dataProvider estadosQueBloquean */
    public function test_estados_que_no_deben_publicarse_en_la_tienda(string $status)
    {
        $this->assertFalse($this->invoke('isPublishable', [$status]), "«{$status}» no debería publicarse");
    }

    public static function estadosQueBloquean(): array
    {
        return [['inactive'], ['deleted'], ['rejected'], ['disapproved']];
    }

    /** @dataProvider estadosQuePublican */
    public function test_estados_que_si_se_publican(string $status)
    {
        $this->assertTrue($this->invoke('isPublishable', [$status]), "«{$status}» debería publicarse");
    }

    public static function estadosQuePublican(): array
    {
        return [
            ['active'],
            // Producto vivo que se quedó sin stock: se publica igual.
            ['sold-out'],
            // Estado desconocido o ausente: no castigamos datos que no entendemos.
            ['algo-que-no-conocemos'],
            [''],
        ];
    }

    public function test_el_estado_puede_venir_en_el_producto_o_en_la_unidad_de_negocio()
    {
        $this->assertSame('inactive', $this->invoke('resolveStatus', [['Status' => 'Inactive'], []]));
        $this->assertSame('active', $this->invoke('resolveStatus', [[], ['Status' => 'ACTIVE']]));
        $this->assertSame('', $this->invoke('resolveStatus', [[], []]));
    }

    // ── Imágenes ───────────────────────────────────────────────

    public function test_la_galeria_excluye_la_principal_y_se_limita()
    {
        $urls = array_map(fn($i) => "http://img/{$i}.jpg", range(0, 11));

        $r = SagaImageImporter::extractUrls(['MainImage' => 'http://img/0.jpg', 'Images' => ['Image' => $urls]]);

        $this->assertSame('http://img/0.jpg', $r['main']);
        $this->assertNotContains('http://img/0.jpg', $r['gallery'], 'La principal no se repite en la galería');
        $this->assertCount(SagaImageImporter::MAX_GALLERY, $r['gallery']);
    }

    public function test_una_sola_imagen_puede_venir_como_texto_suelto()
    {
        $r = SagaImageImporter::extractUrls(['Images' => ['Image' => 'http://img/sola.jpg']]);

        $this->assertSame('http://img/sola.jpg', $r['main']);
        $this->assertSame([], $r['gallery']);
    }

    public function test_sin_principal_declarada_se_usa_la_primera_de_la_galeria()
    {
        $r = SagaImageImporter::extractUrls(['Images' => ['Image' => ['http://img/a.jpg', 'http://img/b.jpg']]]);

        $this->assertSame('http://img/a.jpg', $r['main']);
        $this->assertSame(['http://img/b.jpg'], $r['gallery']);
    }

    public function test_producto_sin_imagenes_no_rompe()
    {
        $r = SagaImageImporter::extractUrls(['Images' => ['Image' => []]]);

        $this->assertSame('', $r['main']);
        $this->assertSame([], $r['gallery']);
    }
}
