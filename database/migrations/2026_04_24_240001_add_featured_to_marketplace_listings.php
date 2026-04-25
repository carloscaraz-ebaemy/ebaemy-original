<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketplace Fase 3 — productos destacados (publicidad interna).
 *
 * Permite al SuperAdmin marcar listings como "destacados" para que aparezcan
 * arriba del listing principal del marketplace y/o en widgets featured.
 *
 *   is_featured     bool      → si está activo
 *   featured_until  timestamp → expira automáticamente; NULL = sin expirar
 *   featured_score  int       → ordenamiento entre featured (mayor primero)
 *
 * El scope `featured()` filtra por is_featured AND (featured_until IS NULL OR
 * featured_until > NOW). Sin pasarela de pago todavía: la activación es
 * manual desde el panel SuperAdmin.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('sort_score');
            }
            if (!Schema::hasColumn('marketplace_listings', 'featured_until')) {
                $table->timestamp('featured_until')->nullable()->after('is_featured');
            }
            if (!Schema::hasColumn('marketplace_listings', 'featured_score')) {
                $table->unsignedInteger('featured_score')->default(0)->after('featured_until');
            }
        });

        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->index(['is_featured', 'featured_until'], 'mp_listings_featured_idx');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropIndex('mp_listings_featured_idx');
        });

        Schema::table('marketplace_listings', function (Blueprint $table) {
            foreach (['featured_score', 'featured_until', 'is_featured'] as $col) {
                if (Schema::hasColumn('marketplace_listings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
