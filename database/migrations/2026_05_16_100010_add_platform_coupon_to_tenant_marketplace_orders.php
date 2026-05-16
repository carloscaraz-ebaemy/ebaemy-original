<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cupones DE PLATAFORMA aplicados al sub-order de cada tienda.
 * Coexisten con coupon_code/discount_amount (que son del tenant) —
 * los descuentos se suman en el total.
 *
 * platform_coupon_assignment_id apunta a marketplace_user_coupons.id
 * para poder redimir al confirmar pago.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('tenant_marketplace_orders')) return;

        Schema::table('tenant_marketplace_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_marketplace_orders', 'platform_coupon_code')) {
                $table->string('platform_coupon_code', 40)->nullable()->after('discount_amount');
            }
            if (!Schema::hasColumn('tenant_marketplace_orders', 'platform_discount_amount')) {
                $table->decimal('platform_discount_amount', 10, 2)->default(0)->after('platform_coupon_code');
            }
            if (!Schema::hasColumn('tenant_marketplace_orders', 'platform_coupon_assignment_id')) {
                $table->unsignedBigInteger('platform_coupon_assignment_id')->nullable()->after('platform_discount_amount');
                $table->index('platform_coupon_assignment_id', 'tmo_plat_assignment_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenant_marketplace_orders')) return;
        Schema::table('tenant_marketplace_orders', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_marketplace_orders', 'platform_coupon_assignment_id')) {
                $table->dropIndex('tmo_plat_assignment_idx');
                $table->dropColumn('platform_coupon_assignment_id');
            }
            if (Schema::hasColumn('tenant_marketplace_orders', 'platform_discount_amount')) {
                $table->dropColumn('platform_discount_amount');
            }
            if (Schema::hasColumn('tenant_marketplace_orders', 'platform_coupon_code')) {
                $table->dropColumn('platform_coupon_code');
            }
        });
    }
};
