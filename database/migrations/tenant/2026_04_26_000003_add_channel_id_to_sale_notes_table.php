<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `channel_id` a `sale_notes` para tener segmentación por canal
 * de venta en reportes (pos / ecommerce / marketplace / whatsapp).
 *
 * `orders` ya tenía `channel_id` desde 2026_03_23_000003. Esta migración
 * complementa el modelo para que el dashboard tenant pueda mostrar:
 *   - "Ventas por canal" (POS vs ecommerce vs marketplace)
 *   - Análisis de margen por canal
 *   - Reportes filtrados por canal
 *
 * Nullable + sin FK explícita: mismo patrón que orders. Permite poblar
 * gradualmente sin romper NV legacy ni migración heavy.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sale_notes')) return;

        Schema::table('sale_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_notes', 'channel_id')) {
                $table->unsignedInteger('channel_id')->nullable()->after('user_id')
                      ->comment('FK a sales_channels — canal de venta (pos, ecommerce, marketplace, etc.)');
                $table->index('channel_id', 'idx_sn_channel');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sale_notes')) return;

        Schema::table('sale_notes', function (Blueprint $table) {
            if (Schema::hasColumn('sale_notes', 'channel_id')) {
                $table->dropIndex('idx_sn_channel');
                $table->dropColumn('channel_id');
            }
        });
    }
};
