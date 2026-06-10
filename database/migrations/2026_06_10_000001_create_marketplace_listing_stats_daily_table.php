<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot diario de vistas/clicks por producto del marketplace.
 *
 * PROBLEMA QUE RESUELVE
 * ---------------------
 * `marketplace_listings.view_count` y `.click_count` son contadores
 * ACUMULADOS: dicen el total histórico pero no *cuándo* ocurrió cada
 * evento. Por eso era imposible responder "¿cuántas vistas tuvo este
 * producto la semana pasada?" o filtrar la analítica por rango de fechas.
 *
 * Esta tabla agrega una fila por (producto, día) que se incrementa de
 * forma atómica (INSERT ... ON DUPLICATE KEY UPDATE) cada vez que se
 * registra una vista o un click. Es liviana (1 fila/producto/día) e
 * indexada por fecha para que los reportes por rango sean rápidos.
 *
 * Vive en la BD central (system) — igual que marketplace_listings.
 * El tracking es going-forward: los días anteriores al deploy no tienen
 * desglose; para esos rangos el panel cae al acumulado histórico.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_listing_stats_daily')) return;

        Schema::create('marketplace_listing_stats_daily', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('listing_id');
            $table->unsignedInteger('hostname_id')->nullable();
            $table->date('stat_date');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            // Una sola fila por producto y día — la clave del upsert atómico.
            $table->unique(['listing_id', 'stat_date'], 'mls_listing_date_uq');
            // Reportes por rango de fechas (orden cronológico + por producto).
            $table->index(['stat_date', 'listing_id'], 'mls_date_listing_idx');
            $table->index('hostname_id', 'mls_hostname_idx');

            $table->foreign('listing_id')
                  ->references('id')->on('marketplace_listings')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listing_stats_daily');
    }
};
