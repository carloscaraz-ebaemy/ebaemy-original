<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modo del cobro tienda→agencia.
 *
 * Antes el monto solo. Con eso "0" y "vacío" eran el mismo estado y el
 * cliente no veía nada, así que no había forma de decirle GRATIS: el
 * silencio se lee como "me lo cobrarán después", que es justo lo contrario
 * de lo que la tienda quiere comunicar cuando lo regala.
 *
 * Ahora la intención es explícita: amount (cobra) / free (gratis, y se
 * muestra) / hidden (no mencionar el tema).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_settings')
            || Schema::connection('tenant')->hasColumn('shipping_settings', 'agency_fee_mode')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
            $table->string('agency_fee_mode', 10)->default('hidden')->after('agency_fee');
        });

        // Los tenants que ya venían cobrando deben seguir viéndose igual: el
        // que tenga monto queda en 'amount', el resto en 'hidden' (silencio,
        // que es como se comportaba hasta hoy). Nadie estrena "gratis" sin
        // haberlo pedido.
        DB::connection('tenant')->table('shipping_settings')
          ->where('agency_fee', '>', 0)
          ->update(['agency_fee_mode' => 'amount']);
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_settings')
            && Schema::connection('tenant')->hasColumn('shipping_settings', 'agency_fee_mode')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $table->dropColumn('agency_fee_mode');
            });
        }
    }
};
