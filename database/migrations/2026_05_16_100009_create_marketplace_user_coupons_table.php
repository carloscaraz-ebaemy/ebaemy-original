<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Asignacion de un cupon de plataforma a un comprador.
 *
 * Si el coupon esta asignado a este user, lo ve disponible en checkout.
 * Si NO esta asignado pero el coupon scope=platform e is_public=true
 * (futuro), tambien aplicaria — por ahora todo es por asignacion explicita.
 *
 * user_id nullable permite cupones "para cualquiera con el codigo" cuando
 * se quiera, sin asignacion 1:1.
 *
 * Una fila por cada redencion. Asi podemos calcular max_per_user
 * facilmente: COUNT WHERE user_id=X AND coupon_id=Y AND used_at IS NOT NULL.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_user_coupons')) return;

        Schema::create('marketplace_user_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('coupon_id');
            // Snapshot del scope al momento de asignar, por si el coupon
            // cambia de scope despues. Auditoria.
            $table->enum('scope', ['platform', 'tenant'])->default('platform');
            $table->unsignedInteger('tenant_id')->nullable();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamp('used_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            // Referencia opcional al pedido que lo redimio (para
            // reconciliacion). hostname_id + order_id matchean
            // marketplace_user_orders.
            $table->unsignedInteger('redeemed_hostname_id')->nullable();
            $table->unsignedInteger('redeemed_order_id')->nullable();

            $table->foreign('coupon_id')
                  ->references('id')->on('marketplace_coupons')
                  ->onDelete('cascade');

            $table->index(['user_id', 'used_at'], 'mkt_uc_user_status_idx');
            $table->index(['coupon_id', 'used_at'], 'mkt_uc_coupon_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_user_coupons');
    }
};
