<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medición de publicidad del marketplace — IDs de píxel y tokens de las APIs
 * de conversión, administrables desde /admin/marketplace/seo.
 *
 * Nunca se hardcodean en una vista: cada cuenta publicitaria es distinta y el
 * token caduca. Si el campo está vacío, el partial de tracking no emite nada.
 *
 * marketplace_ads_enabled      — interruptor maestro. En 0 no se emite NADA,
 *                                aunque los IDs estén puestos.
 * marketplace_meta_pixel_id    — ID del píxel de Meta (Facebook/Instagram).
 * marketplace_meta_capi_token  — token de la Conversions API (server-side).
 * marketplace_tiktok_pixel_id  — ID del píxel de TikTok.
 * marketplace_tiktok_capi_token— Access Token de la Events API de TikTok.
 * marketplace_ga4_id           — measurement ID de GA4 (G-XXXXXXX).
 * marketplace_ads_test_code    — test_event_code de Meta. Sólo para depurar:
 *                                con él puesto los eventos NO cuentan como
 *                                conversiones reales, así que se vacía al
 *                                terminar la prueba.
 *
 * Los tokens van en `text` porque el de Meta pasa de los 200 caracteres.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('configurations')) return;

        Schema::table('configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('configurations', 'marketplace_ads_enabled')) {
                $table->boolean('marketplace_ads_enabled')->default(false)
                      ->after('marketplace_meta_keywords');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_meta_pixel_id')) {
                $table->string('marketplace_meta_pixel_id', 40)->nullable()
                      ->after('marketplace_ads_enabled');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_meta_capi_token')) {
                $table->text('marketplace_meta_capi_token')->nullable()
                      ->after('marketplace_meta_pixel_id');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_tiktok_pixel_id')) {
                $table->string('marketplace_tiktok_pixel_id', 60)->nullable()
                      ->after('marketplace_meta_capi_token');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_tiktok_capi_token')) {
                $table->text('marketplace_tiktok_capi_token')->nullable()
                      ->after('marketplace_tiktok_pixel_id');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_ga4_id')) {
                $table->string('marketplace_ga4_id', 40)->nullable()
                      ->after('marketplace_tiktok_capi_token');
            }
            if (!Schema::hasColumn('configurations', 'marketplace_ads_test_code')) {
                $table->string('marketplace_ads_test_code', 80)->nullable()
                      ->after('marketplace_ga4_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('configurations')) return;

        Schema::table('configurations', function (Blueprint $table) {
            foreach ([
                'marketplace_ads_test_code',
                'marketplace_ga4_id',
                'marketplace_tiktok_capi_token',
                'marketplace_tiktok_pixel_id',
                'marketplace_meta_capi_token',
                'marketplace_meta_pixel_id',
                'marketplace_ads_enabled',
            ] as $col) {
                if (Schema::hasColumn('configurations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
