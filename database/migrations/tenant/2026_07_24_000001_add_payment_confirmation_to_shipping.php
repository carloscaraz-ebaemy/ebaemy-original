<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Confirmación de pago del envío.
 *
 * Cuando la tienda activa `require_payment`, un envío NO puede avanzar de estado
 * ni subir guía hasta que el encargado confirme el pago. Idempotente por columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_requests')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_requests', $c);
                if (!$has('payment_confirmed'))    $table->boolean('payment_confirmed')->default(false)->after('delivery_price');
                if (!$has('payment_confirmed_at')) $table->timestamp('payment_confirmed_at')->nullable()->after('payment_confirmed');
                if (!$has('payment_note'))         $table->string('payment_note', 255)->nullable()->after('payment_confirmed_at');
            });
        }

        if (Schema::connection('tenant')->hasTable('shipping_settings')
            && !Schema::connection('tenant')->hasColumn('shipping_settings', 'require_payment')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $table->boolean('require_payment')->default(false)->after('agency_fee');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_requests')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                foreach (['payment_confirmed', 'payment_confirmed_at', 'payment_note'] as $c) {
                    if (Schema::connection('tenant')->hasColumn('shipping_requests', $c)) $table->dropColumn($c);
                }
            });
        }
        if (Schema::connection('tenant')->hasTable('shipping_settings')
            && Schema::connection('tenant')->hasColumn('shipping_settings', 'require_payment')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $table->dropColumn('require_payment');
            });
        }
    }
};
