<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Costo del servicio de la tienda de llevar el paquete HASTA LA AGENCIA
 * (envíos a provincia), por paquete. Ej. S/ 20 por paquete.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_settings')
            && !Schema::connection('tenant')->hasColumn('shipping_settings', 'agency_fee')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $table->decimal('agency_fee', 8, 2)->nullable()->after('orders_whatsapp');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_settings')
            && Schema::connection('tenant')->hasColumn('shipping_settings', 'agency_fee')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $table->dropColumn('agency_fee');
            });
        }
    }
};
