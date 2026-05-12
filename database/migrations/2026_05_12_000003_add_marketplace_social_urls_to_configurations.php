<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace social URLs (footer del marketplace y futura página de
 * tienda compartida). Los iconos de Facebook/Instagram/WhatsApp en el
 * footer apuntaban a "#" sin destino real — ahora son administrables
 * desde /admin/marketplace/seo y el footer los oculta si están vacíos.
 *
 * Idempotente: hasColumn antes de cada add — la migración puede
 * re-correrse sin errores si ya se aplicó.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('configurations', 'marketplace_facebook_url')) {
                $table->string('marketplace_facebook_url', 500)->nullable()
                      ->after('marketplace_meta_keywords');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_instagram_url')) {
                $table->string('marketplace_instagram_url', 500)->nullable()
                      ->after('marketplace_facebook_url');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_whatsapp_url')) {
                $table->string('marketplace_whatsapp_url', 500)->nullable()
                      ->after('marketplace_instagram_url');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_tiktok_url')) {
                $table->string('marketplace_tiktok_url', 500)->nullable()
                      ->after('marketplace_whatsapp_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            foreach (['marketplace_facebook_url', 'marketplace_instagram_url',
                      'marketplace_whatsapp_url', 'marketplace_tiktok_url'] as $col) {
                if (Schema::hasColumn('configurations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
