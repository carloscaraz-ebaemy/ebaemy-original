<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envío gratuito por umbral de monto (ecommerce del tenant).
 *
 * Agrega `ecommerce_free_shipping_threshold` a configurations: si el subtotal
 * del pedido (tras descuentos, antes de envío) alcanza este monto, el envío
 * a domicilio sale 0. NULL/0 = desactivado. Idempotente.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('configurations', 'ecommerce_free_shipping_threshold')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->decimal('ecommerce_free_shipping_threshold', 12, 2)->nullable()
                      ->comment('Umbral S/ para envío gratis a domicilio. NULL/0 = desactivado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configurations', 'ecommerce_free_shipping_threshold')) {
            Schema::table('configurations', function (Blueprint $table) {
                $table->dropColumn('ecommerce_free_shipping_threshold');
            });
        }
    }
};
