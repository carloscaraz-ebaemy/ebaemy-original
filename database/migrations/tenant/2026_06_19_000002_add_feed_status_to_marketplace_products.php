<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado asíncrono de publicación en Saga (Fase 3).
 *
 * ProductCreate de Seller Center es ASÍNCRONO: devuelve un FeedId, el feed se
 * procesa y el producto pasa por control de calidad (QC). Estas columnas
 * permiten rastrear ese ciclo por producto:
 *   - feed_id: id del feed devuelto por ProductCreate.
 *   - qc_status: queued|processing|rejected|approved|live (estado en Saga).
 *   - rejection_reasons: errores devueltos por FeedStatus/QC (json).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_products')) {
            return;
        }

        Schema::table('marketplace_products', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_products', 'feed_id')) {
                $table->string('feed_id', 60)->nullable()->after('external_id');
            }
            if (!Schema::hasColumn('marketplace_products', 'qc_status')) {
                $table->string('qc_status', 30)->nullable()->after('sync_status');
            }
            if (!Schema::hasColumn('marketplace_products', 'rejection_reasons')) {
                $table->json('rejection_reasons')->nullable()->after('last_error');
            }
        });

        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->index('feed_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_products')) {
            return;
        }

        Schema::table('marketplace_products', function (Blueprint $table) {
            $table->dropIndex(['feed_id']);
            $table->dropColumn(['feed_id', 'qc_status', 'rejection_reasons']);
        });
    }
};
