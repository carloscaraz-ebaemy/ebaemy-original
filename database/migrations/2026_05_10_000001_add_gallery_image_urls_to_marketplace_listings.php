<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * gallery_image_urls — array JSON de URLs (3-5 fotos) de la galería del
 * producto. La card del marketplace lo usa para hacer un slideshow al
 * hover (estilo AliExpress / TikTok Shop). Si no hay galería, NULL y
 * la card mantiene el hover-image simple (primary → secondary).
 *
 * Se llena desde item_images del tenant en MarketplaceListingSyncService::buildPayload.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_listings')) return;
        if (Schema::hasColumn('marketplace_listings', 'gallery_image_urls')) return;

        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->json('gallery_image_urls')->nullable()->after('secondary_image_url');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_listings')) return;
        if (!Schema::hasColumn('marketplace_listings', 'gallery_image_urls')) return;

        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn('gallery_image_urls');
        });
    }
};
