<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Espejo de item_variants.is_primary en system. El sync propaga la marca
 * al guardar la variante en marketplace_listing_variants. La UI del
 * marketplace la usa para:
 *
 *  - Elegir qué imagen mostrar en la card del listado (la del primary).
 *  - Resaltar el dot/thumb activo en el card.
 *
 * Si ningún variant tiene is_primary=true, el código cae al fallback
 * "primera variante con stock>0+image_url" para no romper el render.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_listing_variants')) return;
        if (Schema::hasColumn('marketplace_listing_variants', 'is_primary')) return;

        Schema::table('marketplace_listing_variants', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('is_active');
            $table->index(['listing_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_listing_variants')) return;
        if (!Schema::hasColumn('marketplace_listing_variants', 'is_primary')) return;

        Schema::table('marketplace_listing_variants', function (Blueprint $table) {
            $table->dropIndex(['listing_id', 'is_primary']);
            $table->dropColumn('is_primary');
        });
    }
};
