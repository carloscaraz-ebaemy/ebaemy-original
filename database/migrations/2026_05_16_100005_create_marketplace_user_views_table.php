<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de productos vistos por comprador. INSERT-only desde un job
 * en queue (NUNCA sincrono — un view nunca debe frenar el render).
 *
 * Retencion: 90 dias. Job diario PurgeOldMarketplaceUserViews borra
 * filas con viewed_at < now-90d.
 *
 * Particionado mensual: MySQL soporta PARTITION BY RANGE pero requiere
 * mantenimiento manual (crear particiones futuras, drop viejas). Para
 * volumen actual (<1M filas) un indice (user_id, viewed_at desc) es
 * suficiente. Cuando el volumen lo justifique, se migra a partitions.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_user_views')) return;

        Schema::create('marketplace_user_views', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('hostname_id')->nullable();
            $table->unsignedBigInteger('listing_id');
            $table->timestamp('viewed_at')->useCurrent();
            $table->string('session_id', 80)->nullable();
            $table->string('referrer', 255)->nullable();

            $table->index(['user_id', 'viewed_at'], 'mkt_views_user_time_idx');
            $table->index(['listing_id', 'viewed_at'], 'mkt_views_listing_time_idx');
            $table->index('viewed_at', 'mkt_views_purge_idx');

            $table->foreign('user_id')
                  ->references('id')->on('marketplace_users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_user_views');
    }
};
