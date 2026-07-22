<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Número de WhatsApp de la tienda para RECIBIR los pedidos que el cliente
 * reenvía desde el formulario público. Si se configura, el botón "Enviar por
 * WhatsApp" del cliente va directo a ese número con todos los datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_settings')
            && !Schema::connection('tenant')->hasColumn('shipping_settings', 'orders_whatsapp')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $table->string('orders_whatsapp', 20)->nullable()->after('min_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_settings')
            && Schema::connection('tenant')->hasColumn('shipping_settings', 'orders_whatsapp')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $table->dropColumn('orders_whatsapp');
            });
        }
    }
};
