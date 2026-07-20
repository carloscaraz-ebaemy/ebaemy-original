<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio del servicio de motorizado (cobro por km).
 *  - tarifas en `shipping_settings`: base + por km, con mínimo.
 *  - `delivery_price` congelado en cada envío a domicilio.
 * Idempotente por columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_settings')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_settings', $c);
                if (!$has('price_per_km')) $table->decimal('price_per_km', 8, 2)->nullable()->after('store_address');
                if (!$has('base_price'))   $table->decimal('base_price', 8, 2)->nullable()->after('price_per_km');
                if (!$has('min_price'))    $table->decimal('min_price', 8, 2)->nullable()->after('base_price');
            });
        }

        if (Schema::connection('tenant')->hasTable('shipping_requests')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                if (!Schema::connection('tenant')->hasColumn('shipping_requests', 'delivery_price')) {
                    $table->decimal('delivery_price', 8, 2)->nullable()->after('duration_text');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_settings')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                foreach (['price_per_km', 'base_price', 'min_price'] as $c) {
                    if (Schema::connection('tenant')->hasColumn('shipping_settings', $c)) $table->dropColumn($c);
                }
            });
        }
        if (Schema::connection('tenant')->hasTable('shipping_requests') && Schema::connection('tenant')->hasColumn('shipping_requests', 'delivery_price')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                $table->dropColumn('delivery_price');
            });
        }
    }
};
