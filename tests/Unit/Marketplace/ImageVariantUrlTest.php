<?php

namespace Tests\Unit\Marketplace;

use App\Services\Marketplace\ImageVariantUrl;
use App\Services\Tenant\ImageProcessingService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Versiones reducidas de las fotos del marketplace: solo se devuelve una URL
 * si el archivo existe, para no pintar un 404 donde antes había foto.
 */
class ImageVariantUrlTest extends TestCase
{
    private const DIR = 'https://alasitas.ebaemy.com/storage/uploads/items/';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(ImageProcessingService::disk());
        ImageVariantUrl::flush();
    }

    protected function tearDown(): void
    {
        ImageVariantUrl::flush();
        parent::tearDown();
    }

    private function guardar(string $file): void
    {
        Storage::disk(ImageProcessingService::disk())->put(ImageProcessingService::BASE_DIR . '/' . $file, 'x');
    }

    public function test_devuelve_la_primera_version_que_existe()
    {
        $this->guardar('foto-abc_medium.webp');

        $this->assertSame(
            self::DIR . 'foto-abc_medium.webp',
            ImageVariantUrl::sibling(self::DIR . 'foto-abc.webp', ['_small', '_medium'])
        );

        $this->guardar('foto-abc_small.webp');
        ImageVariantUrl::flush();

        $this->assertSame(
            self::DIR . 'foto-abc_small.webp',
            ImageVariantUrl::sibling(self::DIR . 'foto-abc.webp', ['_small', '_medium'])
        );
    }

    public function test_quita_el_sufijo_de_tamano_de_la_url_de_partida()
    {
        $this->guardar('foto-abc_small.jpeg');

        // De la miniatura _medium (la de la portada) o de la _mp de la card.
        $this->assertSame(self::DIR . 'foto-abc_small.jpeg', ImageVariantUrl::sibling(self::DIR . 'foto-abc_medium.jpeg', ['_small']));
        $this->assertSame(self::DIR . 'foto-abc_small.jpeg', ImageVariantUrl::sibling(self::DIR . 'foto-abc_mp.jpeg', ['_small']));
    }

    public function test_null_si_no_existe_ninguna_o_la_url_no_es_de_productos()
    {
        $this->assertNull(ImageVariantUrl::sibling(self::DIR . 'nada.webp', ['_small', '_medium']));
        $this->assertNull(ImageVariantUrl::sibling('https://ebaemy.com/logo/logo.jpg', ['_small']));
        $this->assertNull(ImageVariantUrl::sibling(null, ['_small']));
        $this->assertNull(ImageVariantUrl::sibling('', ['_small']));
    }
}
