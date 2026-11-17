<?php

namespace Tests\Unit\Marketplace;

use App\Models\System\PromoVideo;
use Tests\TestCase;

/**
 * Reglas del video promocional que no necesitan base de datos: qué duración
 * se acepta y qué datos de SEO salen.
 */
class PromoVideoTest extends TestCase
{
    public function test_solo_se_aceptan_5_15_o_30_segundos_con_margen_del_encoder()
    {
        $this->assertSame(5, PromoVideo::duracionValida(5.0));
        $this->assertSame(15, PromoVideo::duracionValida(14.6));
        $this->assertSame(30, PromoVideo::duracionValida(30.4));
        $this->assertNull(PromoVideo::duracionValida(20));
        $this->assertNull(PromoVideo::duracionValida(31));
        $this->assertNull(PromoVideo::duracionValida(3));
    }

    public function test_el_videoobject_lleva_lo_que_google_pide()
    {
        $v = new PromoVideo([
            'duration_seconds' => 15,
            'thumbnail_url'    => 'https://cdn/t.jpg',
            'mp4_url'          => 'https://cdn/v.mp4',
            'playback_url'     => 'https://cdn/p',
        ]);
        $v->created_at = now();

        $ld = $v->schemaOrg('Pino Americano', 'https://ebaemy.com/marketplace/item/pino');

        $this->assertSame('VideoObject', $ld['@type']);
        $this->assertSame('Pino Americano', $ld['name']);
        $this->assertSame('PT15S', $ld['duration']);
        foreach (['description', 'thumbnailUrl', 'uploadDate', 'contentUrl', 'embedUrl'] as $campo) {
            $this->assertArrayHasKey($campo, $ld);
        }
    }

    public function test_sin_archivo_no_inventa_urls()
    {
        $v = new PromoVideo(['duration_seconds' => 5]);
        $v->created_at = now();

        $ld = $v->schemaOrg('Cojín');

        $this->assertArrayNotHasKey('contentUrl', $ld);
        $this->assertArrayNotHasKey('embedUrl', $ld);
        $this->assertArrayNotHasKey('url', $ld);
    }
}
