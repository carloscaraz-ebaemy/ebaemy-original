<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Si el comprador estaba logueado al confirmar, persistimos su id
 * para que el dispatcher pueda enviar push de pedido al snapshot
 * cross-tenant (marketplace_user_orders).
 *
 * Nullable: el checkout sigue permitiendo invitados.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_orders')) return;
        if (Schema::hasColumn('marketplace_orders', 'marketplace_user_id')) return;
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('marketplace_user_id')->nullable()->after('customer_email');
            $table->index('marketplace_user_id', 'mp_orders_mkt_user_idx');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_orders')) return;
        if (!Schema::hasColumn('marketplace_orders', 'marketplace_user_id')) return;
        Schema::table('marketplace_orders', function (Blueprint $table) {
            $table->dropIndex('mp_orders_mkt_user_idx');
            $table->dropColumn('marketplace_user_id');
        });
    }
};
