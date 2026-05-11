<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número de WhatsApp del vendedor — se sincroniza desde
 * configuration_ecommerce.whatsapp_vendor_number del tenant en cada sync.
 * La página del producto en el marketplace lo usa para el botón
 * "💬 Contactar al vendedor" que abre wa.me con mensaje pre-llenado.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_listings')) return;
        if (Schema::hasColumn('marketplace_listings', 'seller_whatsapp')) return;

        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->string('seller_whatsapp', 20)->nullable()->after('tenant_verified');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_listings')) return;
        if (!Schema::hasColumn('marketplace_listings', 'seller_whatsapp')) return;

        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn('seller_whatsapp');
        });
    }
};
