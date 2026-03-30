<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade soporte para pre-autorización Culqi (L2).
 *
 * culqi_charge_id  — ID del cargo Culqi (chr_live_xxx / chr_test_xxx)
 *                    Nulo en pagos en efectivo o pre-L2.
 * payment_status   — Estado del pago:
 *                      null            → orden antigua (cobro directo sin pre-auth)
 *                      pending_capture → pre-auth creada, captura pendiente (async job)
 *                      captured        → captura exitosa
 *                      capture_failed  → captura fallida, stock liberado
 *                      cash            → pago en efectivo (sin Culqi)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'culqi_charge_id')) {
                $table->string('culqi_charge_id', 60)->nullable()->after('reference_payment');
            }
            if (!Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status', 20)->nullable()->after('culqi_charge_id');
                $table->index('payment_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropIndex(['payment_status']);
                $table->dropColumn(['culqi_charge_id', 'payment_status']);
            }
        });
    }
};
