<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integración MercadoPago en checkout marketplace.
 *
 * Campos agregados a marketplace_orders:
 *   payment_provider     'mercadopago' (futuro: 'culqi', 'transferencia', etc.)
 *   mp_preference_id     ID de la preferencia (preference) generada en MP
 *   mp_payment_id        ID del pago confirmado tras success/webhook
 *   mp_init_point        URL de checkout para redirigir al comprador
 *   mp_payment_status    estado raw devuelto por MP (approved | pending | rejected | in_process | refunded)
 *   payment_attempted_at primer intento de cobro
 *   payment_paid_at      timestamp del pago confirmado
 *
 * Diseño: las columnas son nullable. Pedidos legacy con payment_status='unpaid'
 * no se ven afectados. Provider 'mercadopago' es default para nuevos.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_orders')) return;

        Schema::table('marketplace_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_orders', 'payment_provider')) {
                $table->string('payment_provider', 30)->nullable()->after('payment_status')
                      ->comment('mercadopago | culqi | transferencia | manual');
            }
            if (!Schema::hasColumn('marketplace_orders', 'mp_preference_id')) {
                $table->string('mp_preference_id', 80)->nullable()->after('payment_provider');
                $table->index('mp_preference_id', 'idx_mo_mp_preference');
            }
            if (!Schema::hasColumn('marketplace_orders', 'mp_payment_id')) {
                $table->string('mp_payment_id', 40)->nullable()->after('mp_preference_id');
                $table->index('mp_payment_id', 'idx_mo_mp_payment');
            }
            if (!Schema::hasColumn('marketplace_orders', 'mp_init_point')) {
                $table->string('mp_init_point', 500)->nullable()->after('mp_payment_id');
            }
            if (!Schema::hasColumn('marketplace_orders', 'mp_payment_status')) {
                $table->string('mp_payment_status', 30)->nullable()->after('mp_init_point');
            }
            if (!Schema::hasColumn('marketplace_orders', 'payment_attempted_at')) {
                $table->timestamp('payment_attempted_at')->nullable()->after('mp_payment_status');
            }
            if (!Schema::hasColumn('marketplace_orders', 'payment_paid_at')) {
                $table->timestamp('payment_paid_at')->nullable()->after('payment_attempted_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_orders')) return;

        Schema::table('marketplace_orders', function (Blueprint $table) {
            foreach ([
                'mp_preference_id'    => 'idx_mo_mp_preference',
                'mp_payment_id'       => 'idx_mo_mp_payment',
            ] as $col => $idx) {
                if (Schema::hasColumn('marketplace_orders', $col)) {
                    $table->dropIndex($idx);
                }
            }
            $table->dropColumn([
                'payment_provider', 'mp_preference_id', 'mp_payment_id',
                'mp_init_point', 'mp_payment_status', 'payment_attempted_at', 'payment_paid_at',
            ]);
        });
    }
};
