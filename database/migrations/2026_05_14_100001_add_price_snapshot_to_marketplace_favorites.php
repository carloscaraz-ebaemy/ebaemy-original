<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot del precio al momento de agregar a favoritos. Sirve para
 * detectar bajadas de precio: si el price actual del listing es menor
 * al snapshot, mostramos badge "¡Bajó de precio!" en /marketplace/favoritos.
 *
 * Idempotente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_favorites', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_favorites', 'price_snapshot')) {
                $table->decimal('price_snapshot', 12, 2)->nullable()->after('listing_id');
            }
            if (!Schema::hasColumn('marketplace_favorites', 'price_drop_seen_at')) {
                // Cuando el usuario VEA la notificacion de bajada, marcamos
                // este timestamp para no seguir mostrando el badge
                // perpetuamente — se reactiva si baja MAS.
                $table->timestamp('price_drop_seen_at')->nullable()->after('price_snapshot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_favorites', function (Blueprint $table) {
            foreach (['price_snapshot', 'price_drop_seen_at'] as $col) {
                if (Schema::hasColumn('marketplace_favorites', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
