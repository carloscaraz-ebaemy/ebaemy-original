<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credenciales MercadoPago por tenant.
 *
 * Cuando un tenant quiere recibir pagos del marketplace en SU propia cuenta
 * MP (en vez de pasar por la cuenta sistema de ebaemy), guarda aquí su
 * access_token y public_key.
 *
 *   mp_access_token   APP_USR-* del seller (cifrado con Crypt si seteado)
 *   mp_public_key     opcional para Bricks
 *   mp_sandbox        true = usar TEST-* tokens
 *   mp_enabled        flag explícito para habilitar/desactivar sin borrar
 *                     credenciales (si alguien quiere pausar temporalmente)
 *
 * Si mp_enabled=false o mp_access_token vacío, MercadoPagoService cae al
 * token system (config('services.mercadopago.access_token')).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'mp_access_token')) {
                $table->text('mp_access_token')->nullable()
                      ->comment('MercadoPago access_token del seller (cifrado)');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'mp_public_key')) {
                $table->string('mp_public_key', 200)->nullable();
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'mp_sandbox')) {
                $table->boolean('mp_sandbox')->default(false);
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'mp_enabled')) {
                $table->boolean('mp_enabled')->default(false)
                      ->comment('true = usar credenciales del tenant; false = caer a system');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            foreach (['mp_access_token', 'mp_public_key', 'mp_sandbox', 'mp_enabled'] as $col) {
                if (Schema::hasColumn('configuration_ecommerce', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
