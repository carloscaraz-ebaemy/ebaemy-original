<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte para cupones en el checkout del marketplace.
 *
 * Hasta ahora los cupones solo se aplicaban dentro del tenant después de
 * que el subpedido le llegaba. El cliente del marketplace no podía
 * ingresar un código de cupón en el checkout público — perdíamos
 * conversión y los sellers no podían correr campañas como "EBA10".
 *
 * Cambios:
 *   marketplace_orders.discount_total      = suma de descuentos de todas las tiendas
 *   tenant_marketplace_orders.coupon_code  = código aplicado (snapshot, audit)
 *   tenant_marketplace_orders.discount_amount = descuento en S/ aplicado
 *
 * Idempotente (hasColumn).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_orders')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('marketplace_orders', 'discount_total')) {
                    $table->decimal('discount_total', 12, 2)->default(0)->after('subtotal')
                          ->comment('Suma de descuentos por cupón aplicados en todas las tiendas');
                }
            });
        }

        if (Schema::hasTable('tenant_marketplace_orders')) {
            Schema::table('tenant_marketplace_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('tenant_marketplace_orders', 'coupon_code')) {
                    $table->string('coupon_code', 60)->nullable()->after('subtotal')
                          ->comment('Código del cupón aplicado en checkout (snapshot)');
                }
                if (!Schema::hasColumn('tenant_marketplace_orders', 'discount_amount')) {
                    $table->decimal('discount_amount', 12, 2)->default(0)->after('coupon_code')
                          ->comment('Monto descontado por cupón en esta subtienda');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_orders')) {
            Schema::table('marketplace_orders', function (Blueprint $table) {
                if (Schema::hasColumn('marketplace_orders', 'discount_total')) {
                    $table->dropColumn('discount_total');
                }
            });
        }
        if (Schema::hasTable('tenant_marketplace_orders')) {
            Schema::table('tenant_marketplace_orders', function (Blueprint $table) {
                foreach (['discount_amount', 'coupon_code'] as $col) {
                    if (Schema::hasColumn('tenant_marketplace_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
