<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_notes', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_notes', 'shipping_packages')) {
                $table->unsignedSmallInteger('shipping_packages')
                      ->default(1)
                      ->after('shipping_notes')
                      ->comment('Número de bultos/paquetes');
            }

            if (!Schema::hasColumn('sale_notes', 'shipping_price_package')) {
                $table->decimal('shipping_price_package', 10, 2)
                      ->default(0)
                      ->after('shipping_packages')
                      ->comment('Precio por paquete (no facturado)');
            }

            if (!Schema::hasColumn('sale_notes', 'shipping_cost')) {
                $table->decimal('shipping_cost', 10, 2)
                      ->default(0)
                      ->after('shipping_price_package')
                      ->comment('Costo total de envío = paquetes × precio (no entra al comprobante)');
            }

            if (!Schema::hasColumn('sale_notes', 'shipping_cost_paid')) {
                $table->boolean('shipping_cost_paid')
                      ->default(false)
                      ->after('shipping_cost')
                      ->comment('¿El costo de envío fue cobrado en caja?');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_notes', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_packages',
                'shipping_price_package',
                'shipping_cost',
                'shipping_cost_paid',
            ]);
        });
    }
};
