<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDiscountFieldsToOrdersTable extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->nullable()->after('total')
                      ->comment('Total antes de descuentos');
            }
            if (!Schema::hasColumn('orders', 'total_discount')) {
                $table->decimal('total_discount', 12, 2)->default(0)->after('subtotal')
                      ->comment('Descuento total (cupón + reglas + puntos)');
            }
            if (!Schema::hasColumn('orders', 'coupon_code')) {
                $table->string('coupon_code', 50)->nullable()->after('total_discount')
                      ->comment('Código de cupón aplicado (referencia)');
            }
            if (!Schema::hasColumn('orders', 'discounts')) {
                $table->json('discounts')->nullable()->after('coupon_code')
                      ->comment('Desglose de descuentos aplicados (breakdown PromotionEngine)');
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'total_discount', 'coupon_code', 'discounts']);
        });
    }
}
