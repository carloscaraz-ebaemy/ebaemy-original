<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Códigos de verificación de dominio de Meta, Google y TikTok para ebaemy.com.
 *
 * Las tres plataformas aceptan verificar el dominio con una <meta> en la
 * portada en vez de un registro DNS. Se pegan en /admin/marketplace/seo y el
 * layout del marketplace los emite en <head> (la raíz redirige a /marketplace).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('configurations')) {
            return;
        }
        Schema::table('configurations', function (Blueprint $table) {
            foreach (['marketplace_fb_domain_verification', 'marketplace_google_site_verification', 'marketplace_tiktok_site_verification'] as $col) {
                if (!Schema::hasColumn('configurations', $col)) {
                    $table->string($col, 120)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('configurations')) {
            return;
        }
        $cols = array_values(array_filter(
            ['marketplace_fb_domain_verification', 'marketplace_google_site_verification', 'marketplace_tiktok_site_verification'],
            fn ($c) => Schema::hasColumn('configurations', $c)
        ));
        if ($cols) {
            Schema::table('configurations', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};
