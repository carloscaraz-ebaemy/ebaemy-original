<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «¿Cómo te pagan?» — los medios de pago que la tienda acepta cuando el
 * pedido llega por el marketplace (2026-11-25).
 *
 * Decisión del dueño: en ebaemy.com NADIE cobra; cada tienda coordina el
 * pago con el comprador. Hasta ahora el comprador terminaba el pedido sin
 * saber CÓMO pagarle a la tienda, y la tienda tenía que explicarlo por
 * WhatsApp pedido a pedido. Viven junto a la política de envío porque viajan
 * por el mismo camino: StoreShipping::summary() → listing.shipping['pay'] →
 * carrito → confirmación del pedido.
 *
 * Nada es obligatorio: sin nada marcado se sigue diciendo «La tienda te
 * indica cómo pagar», que es la verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_settings')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
            $s = Schema::connection('tenant');
            $add = function (string $col, callable $def) use ($s) {
                if (!$s->hasColumn('shipping_settings', $col)) {
                    $def();
                }
            };

            $add('pay_yape_phone', fn () => $table->string('pay_yape_phone', 20)->nullable());
            $add('pay_yape_name', fn () => $table->string('pay_yape_name', 80)->nullable());
            $add('pay_plin_phone', fn () => $table->string('pay_plin_phone', 20)->nullable());
            $add('pay_plin_name', fn () => $table->string('pay_plin_name', 80)->nullable());
            // Banco, titular, cuenta y CCI en texto libre: cada banco lo escribe distinto.
            $add('pay_transfer', fn () => $table->string('pay_transfer', 300)->nullable());
            $add('pay_cash', fn () => $table->boolean('pay_cash')->default(false));
            $add('pay_card', fn () => $table->boolean('pay_card')->default(false));
            $add('pay_notes', fn () => $table->string('pay_notes', 200)->nullable());
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_settings')) {
            return;
        }

        $cols = array_values(array_filter([
            'pay_yape_phone', 'pay_yape_name', 'pay_plin_phone', 'pay_plin_name',
            'pay_transfer', 'pay_cash', 'pay_card', 'pay_notes',
        ], fn ($c) => Schema::connection('tenant')->hasColumn('shipping_settings', $c)));

        if ($cols) {
            Schema::connection('tenant')->table('shipping_settings', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};
