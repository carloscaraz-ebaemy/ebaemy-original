<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking de recordatorios de pago para pedidos abandonados.
 *
 * Un pedido marketplace que quedó en status=pending + payment_status=unpaid
 * y no se completó pasadas las 2h: lo consideramos abandonado y enviamos
 * un email recordatorio al comprador.
 *
 * Límites para no spammear:
 *   - reminder_count máximo: 2 (espaciados 24h+)
 *   - solo si created_at > NOW() - 7 días (sin reanimar zombies)
 *
 * Idempotente.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_orders', 'reminder_sent_at')) {
                $table->timestamp('reminder_sent_at')->nullable()->after('source_ua');
            }
            if (!Schema::hasColumn('marketplace_orders', 'reminder_count')) {
                $table->unsignedTinyInteger('reminder_count')->default(0)->after('reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_orders', function (Blueprint $table) {
            foreach (['reminder_sent_at', 'reminder_count'] as $col) {
                if (Schema::hasColumn('marketplace_orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
