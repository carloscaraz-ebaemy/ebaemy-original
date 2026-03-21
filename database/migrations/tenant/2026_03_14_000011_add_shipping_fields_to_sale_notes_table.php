<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->table('sale_notes', function (Blueprint $table) {
            // Datos de envío que el vendedor recoge del cliente
            $table->string('shipping_recipient', 150)->nullable()->after('warehouse_notes')
                  ->comment('Nombre de quien recibirá el paquete');
            $table->string('shipping_phone', 20)->nullable()->after('shipping_recipient')
                  ->comment('Teléfono del destinatario');
            $table->string('shipping_address', 250)->nullable()->after('shipping_phone')
                  ->comment('Dirección completa de entrega');
            $table->string('shipping_city', 100)->nullable()->after('shipping_address')
                  ->comment('Ciudad / distrito de destino');
            $table->string('preferred_courier', 120)->nullable()->after('shipping_city')
                  ->comment('Courier preferido indicado por el cliente');
            $table->text('shipping_notes')->nullable()->after('preferred_courier')
                  ->comment('Instrucciones especiales: frágil, no doblar, etc.');
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('sale_notes', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_recipient',
                'shipping_phone',
                'shipping_address',
                'shipping_city',
                'preferred_courier',
                'shipping_notes',
            ]);
        });
    }
};
