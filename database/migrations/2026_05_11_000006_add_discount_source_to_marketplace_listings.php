<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega discount_source a marketplace_listings.
 *
 * Hasta ahora is_on_offer + discount_pct decían "hay descuento" pero no
 * de qué tipo: el cliente no sabía si era una flash sale (urgencia) o
 * un descuento permanente del seller. Ahora la UI puede:
 *   - flash_sale: badge animado + countdown
 *   - manual:     mp_price (precio especial fijo del seller)
 *   - rule:       descuento regular por regla de canal
 *
 * Idempotente.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_listings')) return;

        if (!Schema::hasColumn('marketplace_listings', 'discount_source')) {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                $table->string('discount_source', 20)->nullable()->after('discount_pct')
                      ->comment('flash_sale | rule | manual — origen del descuento aplicado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_listings') && Schema::hasColumn('marketplace_listings', 'discount_source')) {
            Schema::table('marketplace_listings', function (Blueprint $table) {
                $table->dropColumn('discount_source');
            });
        }
    }
};
