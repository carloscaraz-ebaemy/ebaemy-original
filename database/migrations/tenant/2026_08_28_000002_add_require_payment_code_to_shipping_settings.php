<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interruptor por tienda: exigir el código de pago al confirmar un registro.
 *
 * Va SEPARADO de `require_payment` a propósito: una tienda puede exigir que se
 * confirme el pago sin llevar control de códigos de operación, y otra querer el
 * control de duplicados. Por defecto queda APAGADO para no cambiarle el flujo a
 * ningún tenant que ya estaba operando. Idempotente por columna.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_settings')
            && !Schema::connection('tenant')->hasColumn('shipping_settings', 'require_payment_code')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $table->boolean('require_payment_code')->default(false)->after('require_payment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasTable('shipping_settings')
            && Schema::connection('tenant')->hasColumn('shipping_settings', 'require_payment_code')) {
            Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
                $table->dropColumn('require_payment_code');
            });
        }
    }
};
