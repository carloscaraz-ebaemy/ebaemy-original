<?php

namespace Tests\Unit\Marketplace;

use App\Models\System\MarketplaceListing;
use Tests\TestCase;

/**
 * Tests de lógica pura del modelo MarketplaceListing.
 * No usan BD — solo accessors + castings.
 * Extiende TestCase (Laravel) para tener app context (url(), config, etc.).
 */
class MarketplaceListingTest extends TestCase
{
    public function test_display_price_prioriza_mp_price_sobre_price()
    {
        $listing = new MarketplaceListing();
        $listing->price = 100.00;
        $listing->mp_price = 80.00;

        $this->assertSame(80.0, $listing->display_price);
    }

    public function test_display_price_cae_a_price_si_mp_price_null()
    {
        $listing = new MarketplaceListing();
        $listing->price = 100.00;
        $listing->mp_price = null;

        $this->assertSame(100.0, $listing->display_price);
    }

    public function test_seller_display_prioriza_tenant_name_sobre_fqdn()
    {
        $listing = new MarketplaceListing();
        $listing->tenant_name = 'Grupo Alasitas';
        $listing->tenant_fqdn = 'alasitas.ebaemy.com';

        $this->assertSame('Grupo Alasitas', $listing->seller_display);
    }

    public function test_seller_display_cae_a_fqdn_si_tenant_name_vacio()
    {
        $listing = new MarketplaceListing();
        $listing->tenant_name = null;
        $listing->tenant_fqdn = 'alasitas.ebaemy.com';

        $this->assertSame('alasitas.ebaemy.com', $listing->seller_display);
    }

    public function test_conversion_rate_calcula_porcentaje()
    {
        $listing = new MarketplaceListing();
        $listing->click_count = 100;
        $listing->lead_count = 25;

        $this->assertSame(25.0, $listing->conversion_rate);
    }

    public function test_conversion_rate_cero_si_no_hay_clicks()
    {
        $listing = new MarketplaceListing();
        $listing->click_count = 0;
        $listing->lead_count = 5;

        $this->assertSame(0.0, $listing->conversion_rate);
    }

    public function test_tenant_item_url_with_utm_incluye_params()
    {
        $listing = new MarketplaceListing();
        $listing->id = 42;
        $listing->tenant_fqdn = 'alasitas.ebaemy.com';
        $listing->remote_item_id = 123;

        $url = $listing->tenant_item_url_with_utm;

        $this->assertStringContainsString('utm_source=ebaemy_marketplace', $url);
        $this->assertStringContainsString('utm_campaign=listing_42', $url);
        $this->assertStringContainsString('alasitas.ebaemy.com/ecommerce/item/123', $url);
    }

    public function test_casts_convierten_strings_a_tipos_correctos()
    {
        $listing = new MarketplaceListing();
        $casts = $listing->getCasts();

        $this->assertSame('boolean', $casts['is_active']);
        $this->assertSame('boolean', $casts['tenant_verified']);
        $this->assertSame('integer', $casts['rating_count']);
        $this->assertSame('float', $casts['avg_rating']);
        $this->assertSame('float', $casts['mp_price']);
    }

    // ───────── Tokenizado y relevancia textual del buscador ─────────

    public function test_search_tokens_descarta_palabras_de_un_caracter()
    {
        $this->assertSame(['polo', 'manga'], MarketplaceListing::searchTokens('polo a manga'));
    }

    public function test_search_tokens_colapsa_espacios_y_devuelve_vacio_sin_query()
    {
        $this->assertSame(['polo', 'rojo'], MarketplaceListing::searchTokens('  polo   rojo '));
        $this->assertSame([], MarketplaceListing::searchTokens(null));
        $this->assertSame([], MarketplaceListing::searchTokens('   '));
    }

    public function test_search_tokens_conserva_el_token_de_un_caracter_si_es_el_unico()
    {
        $this->assertSame(['x'], MarketplaceListing::searchTokens('x'));
    }

    public function test_text_relevance_puntua_frontera_de_palabra_por_encima_del_substring()
    {
        $sql = MarketplaceListing::textRelevanceSql('polo');

        // El titulo manda: empieza por el token → 6, palabra del titulo → 5.
        $this->assertStringContainsString("WHEN title LIKE 'polo%'", $sql);
        $this->assertStringContainsString('THEN 6', $sql);
        $this->assertStringContainsString("title LIKE '% polo%'", $sql);
        $this->assertStringContainsString('THEN 5', $sql);
        // El texto indexado (marca + categoria + variantes) va por debajo,
        // y siempre al inicio de palabra.
        $this->assertStringContainsString("search_text REGEXP '(^|[^[:alnum:]])polo' THEN 4", $sql);
        // Y el substring suelto (el caso "espolon") se queda en el suelo.
        $this->assertStringContainsString('ELSE 1 END', $sql);
    }

    public function test_text_relevance_pone_la_palabra_por_encima_de_sus_sinonimos()
    {
        $sql = MarketplaceListing::textRelevanceSql('polo');

        // Un sinonimo en el titulo (3) puntua menos que la palabra tecleada
        // en cualquier sitio (4+): «camiseta» no empata con «polo».
        $palabra  = strpos($sql, "search_text REGEXP '(^|[^[:alnum:]])polo' THEN 4");
        $sinonimo = strpos($sql, "title LIKE 'camiseta%'");

        $this->assertNotFalse($palabra);
        $this->assertNotFalse($sinonimo);
        $this->assertLessThan($sinonimo, $palabra);
        $this->assertStringContainsString("search_text REGEXP '(^|[^[:alnum:]])camiseta' OR", $sql);
    }

    public function test_text_relevance_bonifica_la_palabra_entera()
    {
        $sql = MarketplaceListing::textRelevanceSql('cama');

        // «Cama de madera» antes que «Cámara»: +2 si la palabra sale entera.
        $this->assertStringContainsString(
            "(CASE WHEN search_text REGEXP '(^|[^[:alnum:]])cama(s|es)?([^[:alnum:]]|$)'",
            $sql
        );
    }

    public function test_text_relevance_suma_un_termino_por_token()
    {
        $uno = MarketplaceListing::textRelevanceSql('polo');
        $dos = MarketplaceListing::textRelevanceSql('polo rojo');

        // Tres CASE por token: exactitud, palabra entera y los tramos.
        $this->assertSame(3, substr_count($uno, '(CASE WHEN'));
        $this->assertSame(6, substr_count($dos, '(CASE WHEN'));
        $this->assertStringContainsString(') + (', $dos);
    }

    public function test_search_tokens_descarta_las_palabras_de_relleno()
    {
        $this->assertSame(['zapatillas', 'niña'], MarketplaceListing::searchTokens('zapatillas para niña'));
        $this->assertSame(['polo', 'rojo'], MarketplaceListing::searchTokens('polo,rojo'));
        // Si todo es relleno, se busca tal cual en vez de no buscar nada.
        $this->assertSame(['para', 'con'], MarketplaceListing::searchTokens('para con'));
    }

    public function test_text_relevance_bonifica_el_token_exacto_sobre_el_sinonimo()
    {
        $sql = MarketplaceListing::textRelevanceSql('zapatilla');

        // Termino aparte que solo mira la palabra tecleada: asi una zapatilla
        // adelanta a un zapato cuando ambos casan en el titulo.
        $this->assertStringContainsString(
            "(CASE WHEN title LIKE 'zapatilla%' OR title LIKE '% zapatilla%' THEN 1 ELSE 0 END)",
            $sql
        );
    }

    public function test_text_relevance_es_null_sin_query_util()
    {
        $this->assertNull(MarketplaceListing::textRelevanceSql(null));
        $this->assertNull(MarketplaceListing::textRelevanceSql(''));
        // Query sin letras ni digitos: no hay nada que puntuar.
        $this->assertNull(MarketplaceListing::textRelevanceSql('!!'));
    }

    public function test_text_relevance_sanea_comillas_y_comodines_like()
    {
        // Los literales van inline en el SQL: nada que pueda cerrar la cadena
        // ni convertirse en comodin debe sobrevivir.
        $sql = MarketplaceListing::textRelevanceSql("o'brien 50%_x");

        $this->assertStringNotContainsString("o'brien", $sql);
        $this->assertStringContainsString('obrien', $sql);
        $this->assertStringNotContainsString('%_', $sql);
        $this->assertStringContainsString('50x', $sql);
    }
}
