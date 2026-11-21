<?php

namespace Tests\Unit\Marketplace;

use App\Models\System\PromoVideo;
use App\Services\Marketplace\VideoLink;
use App\Services\Marketplace\VideoLinkException;
use Tests\TestCase;

/**
 * Enlaces de video pegados por la tienda: qué se acepta, cómo se normaliza y
 * que el src del iframe sale SIEMPRE del id validado.
 */
class VideoLinkTest extends TestCase
{
    /** @dataProvider aceptados */
    public function test_acepta_y_normaliza(string $input, string $provider, string $id, string $orientation, string $url)
    {
        $l = VideoLink::parse($input);

        $this->assertSame($provider, $l->provider);
        $this->assertSame($id, $l->id);
        $this->assertSame($orientation, $l->orientation);
        $this->assertSame($url, $l->url);
    }

    public static function aceptados(): array
    {
        return [
            'youtube watch'          => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube', 'dQw4w9WgXcQ', 'horizontal', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'youtube watch con más'  => ['https://m.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=10s', 'youtube', 'dQw4w9WgXcQ', 'horizontal', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'youtu.be'               => ['https://youtu.be/dQw4w9WgXcQ?si=abc123', 'youtube', 'dQw4w9WgXcQ', 'horizontal', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'shorts'                 => ['https://youtube.com/shorts/aBcDeFgHi_-?feature=share', 'youtube', 'aBcDeFgHi_-', 'vertical', 'https://www.youtube.com/shorts/aBcDeFgHi_-'],
            'sin esquema'            => ['youtube.com/shorts/aBcDeFgHi_-', 'youtube', 'aBcDeFgHi_-', 'vertical', 'https://www.youtube.com/shorts/aBcDeFgHi_-'],
            'tiktok largo'           => ['https://www.tiktok.com/@alasitas.pe/video/7301234567890123456?is_from_webapp=1&sender_device=pc', 'tiktok', '7301234567890123456', 'vertical', 'https://www.tiktok.com/@alasitas.pe/video/7301234567890123456'],
            'texto de compartir'     => ['Mira este video 😍 https://www.tiktok.com/@tienda_x/video/7301234567890123456 #oferta', 'tiktok', '7301234567890123456', 'vertical', 'https://www.tiktok.com/@tienda_x/video/7301234567890123456'],
            'instagram reel'         => ['https://www.instagram.com/reel/C1a2B3c4D5e/?igsh=MWQ1ZGUx', 'instagram', 'C1a2B3c4D5e', 'vertical', 'https://www.instagram.com/reel/C1a2B3c4D5e/'],
            'instagram reels'        => ['https://instagram.com/reels/C1a2B3c4D5e', 'instagram', 'C1a2B3c4D5e', 'vertical', 'https://www.instagram.com/reel/C1a2B3c4D5e/'],
        ];
    }

    /** @dataProvider rechazados */
    public function test_rechaza_con_mensaje_claro(string $input)
    {
        try {
            VideoLink::parse($input);
            $this->fail('Debió rechazar: ' . $input);
        } catch (VideoLinkException $e) {
            $this->assertNull($e->shortUrl);
            $this->assertNotSame('', $e->getMessage());
        }
    }

    public static function rechazados(): array
    {
        return [
            'vacío'                  => [''],
            'otro dominio'           => ['https://vimeo.com/123456789'],
            'dominio parecido'       => ['https://www.youtube.com.evil.com/watch?v=dQw4w9WgXcQ'],
            'subdominio falso'       => ['https://evilyoutube.com/watch?v=dQw4w9WgXcQ'],
            'javascript'             => ['javascript:alert(1)//youtube.com/watch?v=dQw4w9WgXcQ'],
            'ftp'                    => ['ftp://youtube.com/watch?v=dQw4w9WgXcQ'],
            'con usuario'            => ['https://user:pass@www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'puerto raro'            => ['https://www.youtube.com:8080/watch?v=dQw4w9WgXcQ'],
            'canal de youtube'       => ['https://www.youtube.com/@ebaemy'],
            'id corto'               => ['https://www.youtube.com/watch?v=abc'],
            'id con comillas'        => ['https://youtu.be/dQw4w9WgX"Q'],
            'playlist'               => ['https://www.youtube.com/playlist?list=PL123'],
            'perfil de tiktok'       => ['https://www.tiktok.com/@alasitas.pe'],
            'fotos de tiktok'        => ['https://www.tiktok.com/@alasitas.pe/photo/7301234567890123456'],
            'id tiktok no numérico'  => ['https://www.tiktok.com/@a/video/73012abc'],
            'post de instagram'      => ['https://www.instagram.com/p/C1a2B3c4D5e/'],
            'perfil de instagram'    => ['https://www.instagram.com/alasitas.pe/'],
            'texto sin enlace'       => ['mi video del producto'],
        ];
    }

    public function test_enlace_corto_de_tiktok_pide_resolverse()
    {
        foreach ([
            'https://vm.tiktok.com/ZMabc123/' => 'https://vm.tiktok.com/ZMabc123/',
            'vt.tiktok.com/ZSxyz789'         => 'https://vt.tiktok.com/ZSxyz789/',
            'https://www.tiktok.com/t/ZT8abcd/' => 'https://www.tiktok.com/t/ZT8abcd/',
        ] as $input => $short) {
            try {
                VideoLink::parse($input);
                $this->fail('Debió pedir resolver: ' . $input);
            } catch (VideoLinkException $e) {
                $this->assertSame($short, $e->shortUrl);
            }
        }
    }

    public function test_el_src_del_iframe_sale_solo_del_id_validado()
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', VideoLink::embedUrl('youtube', 'dQw4w9WgXcQ'));
        $this->assertSame('https://www.tiktok.com/player/v1/7301234567890123456', VideoLink::embedUrl('tiktok', '7301234567890123456'));
        $this->assertSame('https://www.instagram.com/reel/C1a2B3c4D5e/embed', VideoLink::embedUrl('instagram', 'C1a2B3c4D5e'));

        // Una fila manipulada en la base no llega a un iframe.
        $this->assertNull(VideoLink::embedUrl('youtube', 'x"><script>'));
        $this->assertNull(VideoLink::embedUrl('tiktok', '../../evil'));
        $this->assertNull(VideoLink::embedUrl('vimeo', '123456789'));
        $this->assertNull(VideoLink::embedUrl(null, null));
    }

    public function test_miniatura_fija_solo_en_youtube()
    {
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', VideoLink::staticThumbnail('youtube', 'dQw4w9WgXcQ'));
        $this->assertNull(VideoLink::staticThumbnail('tiktok', '7301234567890123456'));
    }

    public function test_el_video_por_enlace_arma_su_videoobject()
    {
        $v = new PromoVideo([
            'kind'              => 'link',
            'provider'          => 'youtube',
            'provider_video_id' => 'aBcDeFgHi_-',
            'playback_url'      => 'https://www.youtube-nocookie.com/embed/aBcDeFgHi_-',
            'thumbnail_url'     => 'https://i.ytimg.com/vi/aBcDeFgHi_-/hqdefault.jpg',
            'aspect'            => 'vertical',
        ]);
        $v->created_at = now();

        $ld = $v->schemaOrg('Pino Americano', 'https://ebaemy.com/marketplace/item/pino', 'https://tienda/foto.jpg');

        $this->assertSame('https://www.youtube-nocookie.com/embed/aBcDeFgHi_-', $ld['embedUrl']);
        $this->assertSame('https://i.ytimg.com/vi/aBcDeFgHi_-/hqdefault.jpg', $ld['thumbnailUrl']);
        $this->assertArrayNotHasKey('duration', $ld);    // de un enlace no se sabe
        $this->assertArrayNotHasKey('contentUrl', $ld);  // no hay archivo propio
        $this->assertTrue($v->isVertical());
    }

    public function test_sin_miniatura_usa_la_foto_del_producto_y_no_confia_en_playback_url()
    {
        $v = new PromoVideo([
            'kind'              => 'link',
            'provider'          => 'instagram',
            'provider_video_id' => 'C1a2B3c4D5e',
            // Aunque alguien cambiara playback_url en la base, el src sale del id.
            'playback_url'      => 'https://evil.example/embed',
        ]);
        $v->created_at = now();

        $this->assertSame('https://www.instagram.com/reel/C1a2B3c4D5e/embed', $v->embedSrc());
        $this->assertSame('https://tienda/foto.jpg', $v->schemaOrg('Cojín', null, 'https://tienda/foto.jpg')['thumbnailUrl']);
    }
}
