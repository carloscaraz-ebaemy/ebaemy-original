<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistic_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistic_order_id')->index();
            $table->unsignedInteger('item_id')->index();
            $table->unsignedInteger('warehouse_id');

            $table->string('description');
            $table->string('unit_type_id', 10)->default('NIU');

            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('unit_price_with_igv', 12, 4)->default(0);

            // Facturación: tipo de afectación IGV (10=Gravado, 20=Exonerado, 30=Inafecto)
            $table->string('affectation_igv_type_id', 5)->default('10');
            $table->decimal('total_base_igv', 12, 4)->default(0);
            $table->decimal('total_igv', 12, 4)->default(0);
            $table->decimal('total', 12, 4)->default(0);

            $table->timestamps();

            $table->foreign('logistic_order_id')
                  ->references('id')->on('logistic_orders')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_order_items');
    }
};
