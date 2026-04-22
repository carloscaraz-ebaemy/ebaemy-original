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
}
