<?php

namespace Tests\Unit;

use App\Services\Tenant\ImageProcessingService;
use PHPUnit\Framework\TestCase;

/**
 * El fondo de la imagen principal: blanco o transparente pasa, el resto no.
 *
 * Las imágenes se dibujan aquí con GD: un «producto» oscuro en el centro y el
 * fondo que toque. Así el test no depende de fotos en disco.
 */
class ImageBackgroundTest extends TestCase
{
    private function imagen(int $w, int $h, ?array $fondo, bool $transparente = false, string $formato = 'png'): string
    {
        $img = imagecreatetruecolor($w, $h);

        if ($transparente) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
            imagealphablending($img, true);
        } else {
            imagefill($img, 0, 0, imagecolorallocate($img, ...$fondo));
        }

        // El producto: un rectángulo oscuro que ocupa el centro.
        imagefilledrectangle($img, (int) ($w * .2), (int) ($h * .15), (int) ($w * .8), (int) ($h * .85),
            imagecolorallocate($img, 40, 60, 120));

        ob_start();
        $formato === 'jpg' ? imagejpeg($img, null, 80) : imagepng($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    /** @test */
    public function fondo_blanco_pasa()
    {
        $r = ImageProcessingService::analyzeBackground($this->imagen(800, 600, [255, 255, 255], false, 'jpg'));

        $this->assertTrue($r['ok']);
        $this->assertSame('blanco', $r['tipo']);
    }

    /** @test */
    public function blanco_roto_de_estudio_pasa()
    {
        $r = ImageProcessingService::analyzeBackground($this->imagen(600, 800, [242, 241, 240], false, 'jpg'));

        $this->assertTrue($r['ok']);
    }

    /** @test */
    public function fondo_transparente_pasa()
    {
        $r = ImageProcessingService::analyzeBackground($this->imagen(500, 500, null, true));

        $this->assertTrue($r['ok']);
        $this->assertSame('transparente', $r['tipo']);
    }

    /** @test */
    public function fondo_de_color_no_pasa()
    {
        $r = ImageProcessingService::analyzeBackground($this->imagen(800, 800, [200, 30, 40], false, 'jpg'));

        $this->assertFalse($r['ok']);
        $this->assertSame('otro', $r['tipo']);
        $this->assertLessThan(0.1, $r['proporcion']);
    }

    /** @test */
    public function gris_claro_no_es_blanco()
    {
        $r = ImageProcessingService::analyzeBackground($this->imagen(800, 800, [210, 210, 210], false, 'jpg'));

        $this->assertFalse($r['ok']);
    }

    /** @test */
    public function producto_que_toca_el_borde_inferior_aun_pasa()
    {
        // Producto apoyado abajo: ocupa una de las cuatro franjas entera.
        $img = imagecreatetruecolor(800, 800);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        imagefilledrectangle($img, 300, 200, 500, 799, imagecolorallocate($img, 20, 20, 20));
        ob_start();
        imagepng($img);
        imagedestroy($img);

        $r = ImageProcessingService::analyzeBackground(ob_get_clean());

        $this->assertTrue($r['ok']);
    }

    /** @test */
    public function lo_que_no_es_imagen_devuelve_null()
    {
        $this->assertNull(ImageProcessingService::analyzeBackground('no soy una imagen'));
    }
}
