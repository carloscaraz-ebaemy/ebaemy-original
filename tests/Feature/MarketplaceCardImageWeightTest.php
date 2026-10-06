<?php

namespace Tests\Feature;

use App\Models\System\MarketplaceListing;
use Tests\TestCase;

/**
 * El peso de imagen de la card del marketplace.
 *
 * La card pinta un recuadro de ~260px (48vw en movil) y durante meses sirvio
 * ahi la variante `_mp` de 1080x1080, mas una segunda foto a tamano original
 * que solo se ve al pasar el cursor — y que en movil no se ve nunca. Con 24
 * cards por pagina eso es el grueso de lo que descarga un visitante de la
 * home, y es la primera cosa que se cae con trafico alto.
 *
 * Lo que se prueba no es el HTML por si mismo, son las tres reglas que evitan
 * la recaida:
 *   1. si hay miniatura sincronizada, la card la ofrece via srcset;
 *   2. si NO la hay (producto anterior al pipeline, o la card pinta la foto de
 *      una variante) se cae a la imagen de siempre y no inventa un srcset con
 *      dos fotos distintas;
 *   3. la segunda foto nunca sale con `src` en el HTML inicial.
 *
 * Ver project_imagen_estandar y el analisis de carga del 2026-10-05.
 */
class MarketplaceCardImageWeightTest extends TestCase
{
    private const PARTIAL = 'marketplace.partials.listing-card';

    private const IMG_PADRE  = 'https://alasitas.ebaemy.com/storage/uploads/items/foo_mp.webp';
    private const IMG_THUMB  = 'https://alasitas.ebaemy.com/storage/uploads/items/foo_medium.webp';
    private const IMG_SEGUNDA = 'https://alasitas.ebaemy.com/storage/uploads/items/bar.webp';

    private function card(array $override = []): string
    {
        $listing = new MarketplaceListing();
        $listing->forceFill(array_merge([
            'id'                  => 1,
            'slug'                => 'producto-x',
            'title'               => 'Producto X',
            'hostname_id'         => 3,
            'image_url'           => self::IMG_PADRE,
            'thumb_image_url'     => self::IMG_THUMB,
            'secondary_image_url' => self::IMG_SEGUNDA,
            'price'               => 50,
            'stock'               => 3,
            'status'              => 'active',
            'is_active'           => 1,
            'tenant_name'         => 'Alasitas',
            'tenant_fqdn'         => 'alasitas.ebaemy.com',
        ], $override));

        return view(self::PARTIAL, ['listing' => $listing])->render();
    }

    /** @return string la etiqueta <img> de la clase pedida */
    private function img(string $html, string $clase): string
    {
        $this->assertMatchesRegularExpression(
            '/<img[^>]*' . preg_quote($clase, '/') . '[^>]*>/s',
            $html,
            "la card no tiene ninguna <img> con la clase {$clase}"
        );
        preg_match('/<img[^>]*' . preg_quote($clase, '/') . '[^>]*>/s', $html, $m);

        return preg_replace('/\s+/', ' ', $m[0]);
    }

    public function test_la_card_ofrece_la_miniatura_de_512px(): void
    {
        $img = $this->img($this->card(), 'mp-card-img-primary');

        // src apunta a la miniatura: es lo que se lleva el navegador que
        // ignora srcset, y el candidato por defecto del que si lo entiende.
        $this->assertStringContainsString('src="' . self::IMG_THUMB . '"', $img);
        $this->assertStringContainsString(self::IMG_THUMB . ' 512w', $img);
        $this->assertStringContainsString(self::IMG_PADRE . ' 1080w', $img);
        $this->assertStringContainsString('sizes=', $img);
        // Hueco reservado: sin esto la grilla salta al entrar cada foto.
        $this->assertStringContainsString('width="512"', $img);
        $this->assertStringContainsString('height="512"', $img);
    }

    public function test_sin_miniatura_sincronizada_se_cae_a_la_imagen_de_siempre(): void
    {
        $img = $this->img($this->card(['thumb_image_url' => null]), 'mp-card-img-primary');

        $this->assertStringContainsString('src="' . self::IMG_PADRE . '"', $img);
        $this->assertStringNotContainsString('srcset', $img);
    }

    public function test_la_card_de_una_variante_no_mezcla_dos_fotos_en_el_srcset(): void
    {
        // thumb_image_url es la reducida de la foto del PADRE. Si la card esta
        // pintando la de una variante, ponerlas juntas en el srcset mostraria
        // un producto distinto segun el ancho de la pantalla.
        $variante = 'https://alasitas.ebaemy.com/storage/uploads/items/variante.webp';
        $img = $this->img($this->card(['primary_image_url' => $variante]), 'mp-card-img-primary');

        $this->assertStringContainsString('src="' . $variante . '"', $img);
        $this->assertStringNotContainsString('srcset', $img);
        $this->assertStringNotContainsString(self::IMG_THUMB, $img);
    }

    public function test_la_segunda_foto_no_se_descarga_al_pintar_la_pagina(): void
    {
        $img = $this->img($this->card(), 'mp-card-img-secondary');

        // La URL viaja en data-src; el script la pasa a src al primer
        // acercamiento. Con src= el navegador la bajaria siempre.
        $this->assertStringContainsString('data-src="' . self::IMG_SEGUNDA . '"', $img);
        $this->assertDoesNotMatchRegularExpression('/\ssrc=/', $img);
    }

    public function test_un_producto_sin_foto_sigue_avisando_en_la_card(): void
    {
        $html = $this->card(['image_url' => null, 'thumb_image_url' => null, 'secondary_image_url' => null]);

        $this->assertStringContainsString('Sin imagen', $html);
        $this->assertStringNotContainsString('mp-card-img-primary', $html);
    }
}
