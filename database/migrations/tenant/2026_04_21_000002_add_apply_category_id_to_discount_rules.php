<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Habilita el scope "category" en discount_rules permitiendo vincular la regla
 * a una categoría concreta (tabla `categories`). La columna se usa solo cuando
 * applies_to = 'category'; el cálculo lo hace DiscountRule::calculateScopedDiscount.
 */
class AddApplyCategoryIdToDiscountRules extends Migration
{
    public function up()
    {
        Schema::table('discount_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('discount_rules', 'apply_category_id')) {
                $table->unsignedInteger('apply_category_id')->nullable()->after('apply_item_id')
                      ->comment('FK a categories — solo si applies_to = category');
                $table->index('apply_category_id');
            }
        });
    }

    public function down()
    {
        Schema::table('discount_rules', function (Blueprint $table) {
            if (Schema::hasColumn('discount_rules', 'apply_category_id')) {
                $table->dropIndex(['apply_category_id']);
                $table->dropColumn('apply_category_id');
            }
        });
    }
}
