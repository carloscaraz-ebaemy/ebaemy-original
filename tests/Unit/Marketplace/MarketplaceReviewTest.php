<?php

namespace Tests\Unit\Marketplace;

use App\Models\System\MarketplaceReview;
use Tests\TestCase;

class MarketplaceReviewTest extends TestCase
{
    public function test_casts_convierten_rating_a_int()
    {
        $review = new MarketplaceReview();
        $casts = $review->getCasts();

        $this->assertSame('integer', $casts['rating']);
        $this->assertSame('datetime', $casts['approved_at']);
    }

    public function test_fillable_incluye_campos_esenciales()
    {
        $review = new MarketplaceReview();
        $fillable = $review->getFillable();

        $this->assertContains('listing_id', $fillable);
        $this->assertContains('customer_name', $fillable);
        $this->assertContains('rating', $fillable);
        $this->assertContains('comment', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('source_ip', $fillable);
    }

    public function test_usa_conexion_system()
    {
        $review = new MarketplaceReview();

        // UsesSystemConnection aplica 'system' a la conexión del modelo
        $this->assertSame('system', $review->getConnectionName());
    }
}
