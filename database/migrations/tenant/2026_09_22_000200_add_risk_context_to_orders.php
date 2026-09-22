<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto de riesgo del pedido, para el modulo 1 del agente de seguridad.
 *
 * Sin estas columnas no hay forma de detectar "el pais de la IP no coincide con
 * el envio", "varios pedidos desde la misma IP" ni "una misma tarjeta usada por
 * varios clientes".
 *
 * Sobre la tarjeta: NUNCA se guarda el numero completo. Solo los ultimos cuatro
 * digitos y una huella SHA-256 derivada del BIN + los ultimos cuatro, que sirve
 * para comparar tarjetas entre si sin poder reconstruir ninguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('seller_id');
            }
            if (!Schema::hasColumn('orders', 'user_agent')) {
                $table->string('user_agent', 500)->nullable()->after('ip_address');
            }
            if (!Schema::hasColumn('orders', 'card_last4')) {
                $table->char('card_last4', 4)->nullable()->after('user_agent');
            }
            if (!Schema::hasColumn('orders', 'card_fingerprint')) {
                $table->string('card_fingerprint', 64)->nullable()
                    ->after('card_last4')
                    ->comment('SHA-256 de BIN+last4: compara tarjetas sin guardar el numero');
            }
        });

        $existing = collect(DB::select('SHOW INDEX FROM `orders`'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('orders', function (Blueprint $table) use ($existing) {
            if (!in_array('idx_orders_ip_date', $existing, true)) {
                $table->index(['ip_address', 'created_at'], 'idx_orders_ip_date');
            }
            if (!in_array('idx_orders_card', $existing, true)) {
                $table->index('card_fingerprint', 'idx_orders_card');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_ip_date');
            $table->dropIndex('idx_orders_card');
            $table->dropColumn(['ip_address', 'user_agent', 'card_last4', 'card_fingerprint']);
        });
    }
};
