<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('price1_label')->default('Precio 1')->after('condition_sale_purchase_price_to_item');
            $table->string('price2_label')->default('Precio 2')->after('price1_label');
            $table->string('price3_label')->default('Precio 3')->after('price2_label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn(['price1_label', 'price2_label', 'price3_label']);
        });
    }
};
