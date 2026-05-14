<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de recordatorios al tenant cuando un subpedido del marketplace
 * sigue 'pending' (no atendido) pasadas X horas.
 *
 * Espejo de la migracion que metimos para marketplace_orders (cliente
 * abandonado), pero aplicada al lado tenant — el seller debe atender
 * sus pedidos en su panel, si no lo hace le insistimos por email y
 * WhatsApp hasta un maximo de 3 veces.
 *
 * Idempotente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_marketplace_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_marketplace_orders', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('dispatched_at');
            }
            if (!Schema::hasColumn('tenant_marketplace_orders', 'reminder_count')) {
                $table->unsignedTinyInteger('reminder_count')->default(0)->after('reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_marketplace_orders', function (Blueprint $table) {
            foreach (['reminder_sent_at', 'reminder_count'] as $col) {
                if (Schema::hasColumn('tenant_marketplace_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
