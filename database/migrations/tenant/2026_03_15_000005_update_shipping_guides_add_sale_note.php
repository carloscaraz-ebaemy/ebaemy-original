<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistic_shipping_guides', function (Blueprint $table) {
            // Desvincular la FK para poder hacerla nullable
            $table->dropForeign(['logistic_order_id']);
            $table->unsignedBigInteger('logistic_order_id')->nullable()->change();

            // Nuevo vínculo directo con SaleNote
            $table->unsignedBigInteger('sale_note_id')->nullable()->after('logistic_order_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('logistic_shipping_guides', function (Blueprint $table) {
            $table->dropColumn('sale_note_id');
            $table->unsignedBigInteger('logistic_order_id')->nullable(false)->change();
            $table->foreign('logistic_order_id')->references('id')->on('logistic_orders')->onDelete('cascade');
        });
    }
};
