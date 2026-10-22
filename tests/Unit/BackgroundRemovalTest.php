<?php

namespace Tests\Unit;

use App\Http\Controllers\Tenant\ItemBackgroundController;
use App\Services\Tenant\BackgroundRemoval\BackgroundRemovalException;
use App\Services\Tenant\BackgroundRemoval\BackgroundRemovalService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Quitar el fondo: apagado sin clave, llamada correcta a Photoroom, cupo
 * mensual por tienda, y que el origen del archivo no se pueda forzar.
 */
class BackgroundRemovalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        config([
            'services.background_removal.driver'        => 'photoroom',
            'services.background_removal.photoroom_key' => 'clave-de-prueba',
            'services.background_removal.monthly_limit' => 2,
        ]);
    }

    private function png(): string
    {
        $img = imagecreatetruecolor(20, 20);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    /** @test */
    public function sin_clave_esta_apagado_y_no_llama_a_nadie()
    {
        config(['services.background_removal.photoroom_key' => null]);
        Http::fake();

        $service = new BackgroundRemovalService();
        $this->assertFalse($service->enabled());

        $this->expectException(BackgroundRemovalException::class);
        try {
            $service->remove($this->png());
        } finally {
            Http::assertNothingSent();
        }
    }

    /** @test */
    public function llama_a_photoroom_con_la_clave_y_fondo_blanco_y_descuenta_cupo()
    {
        $salida = $this->png();
        Http::fake(['sdk.photoroom.com/*' => Http::response($salida, 200, ['Content-Type' => 'image/png'])]);

        $service = new BackgroundRemovalService();
        $this->assertTrue($service->enabled());
        $this->assertSame($salida, $service->remove($this->png()));
        $this->assertSame(1, $service->used());
        $this->assertSame(1, $service->remaining());

        Http::assertSent(function (Request $r) {
            $campos = collect($r->data())->pluck('contents', 'name');

            return $r->url() === 'https://sdk.photoroom.com/v1/segment'
                && $r->hasHeader('x-api-key', 'clave-de-prueba')
                && $campos->get('bg_color') === '#FFFFFF'
                && $campos->has('image_file');
        });
    }

    /** @test */
    public function con_el_cupo_agotado_no_se_llama_al_proveedor()
    {
        Http::fake(['sdk.photoroom.com/*' => Http::response($this->png(), 200)]);
        $service = new BackgroundRemovalService();
        $service->remove($this->png());
        $service->remove($this->png());

        try {
            $service->remove($this->png());
            $this->fail('Deberia haber cortado por cupo');
        } catch (BackgroundRemovalException $e) {
            $this->assertStringContainsString('2 fotos de este mes', $e->getMessage());
        }

        Http::assertSentCount(2);
    }

    /** @test */
    public function un_fallo_del_proveedor_no_se_cobra()
    {
        Http::fake(['sdk.photoroom.com/*' => Http::response('{"error":"bad"}', 400)]);
        $service = new BackgroundRemovalService();

        try {
            $service->remove($this->png());
            $this->fail('Deberia haber fallado');
        } catch (BackgroundRemovalException $e) {
            $this->assertStringContainsString('Prueba con otra', $e->getMessage());
        }

        $this->assertSame(0, $service->used());
    }

    /** @test */
    public function el_origen_solo_acepta_temporales_de_subida()
    {
        $controller = new ItemBackgroundController(new BackgroundRemovalService());
        $leer = (new \ReflectionClass($controller))->getMethod('leerOrigen');
        $leer->setAccessible(true);

        // Un temporal nuestro: si.
        $nuestro = tempnam(sys_get_temp_dir(), 'img_');
        file_put_contents($nuestro, 'bytes');
        $this->assertSame('bytes', $leer->invoke($controller, $nuestro, null));

        // Un temporal con otro prefijo: no.
        $ajeno = tempnam(sys_get_temp_dir(), 'otro_');
        file_put_contents($ajeno, 'secreto');
        $this->assertNull($leer->invoke($controller, $ajeno, null));

        // Un archivo fuera del directorio temporal: no.
        $this->assertNull($leer->invoke($controller, base_path('.env'), null));
        $this->assertNull($leer->invoke($controller, sys_get_temp_dir() . '/../img_x', null));

        @unlink($nuestro);
        @unlink($ajeno);
    }
}
