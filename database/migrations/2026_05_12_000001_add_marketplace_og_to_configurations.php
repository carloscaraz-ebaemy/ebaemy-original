<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite al SuperAdmin editar el título/descripción/imagen de Open Graph
 * para el marketplace público (ebaemy.com/marketplace). Sin esto, los
 * compartidos por WhatsApp/Facebook usan el logo y descripción default.
 *
 * marketplace_og_title       — máx 60 chars (recomendado para SERP)
 * marketplace_og_description — máx 160 chars (recomendado para meta desc)
 * marketplace_og_image       — filename del banner 1200×630 en storage/uploads/system/
 * marketplace_meta_keywords  — keywords SEO (separados por coma)
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('configurations')) return;

        Schema::table('configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('configurations', 'marketplace_og_title')) {
                $table->string('marketplace_og_title', 120)->nullable()->after('tenant_image_ads');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_og_description')) {
                $table->string('marketplace_og_description', 250)->nullable()->after('marketplace_og_title');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_og_image')) {
                $table->string('marketplace_og_image', 250)->nullable()->after('marketplace_og_description');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_meta_keywords')) {
                $table->string('marketplace_meta_keywords', 500)->nullable()->after('marketplace_og_image');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('configurations')) return;
        Schema::table('configurations', function (Blueprint $table) {
            foreach (['marketplace_meta_keywords','marketplace_og_image','marketplace_og_description','marketplace_og_title'] as $col) {
                if (Schema::hasColumn('configurations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
