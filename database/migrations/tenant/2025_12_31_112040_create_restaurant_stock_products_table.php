<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Restaurant\Services\RestaurantStockService;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('restaurant_stock_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('item_id');
            $table->decimal('stock', 12, 4)->default(0); // puede venir de item_warehouse o de model-item getRestaurantStock
            $table->decimal('quantity_reserved', 12, 4)->default(0); // cantidades en mesa sin factura
            $table->boolean('has_supplies')->default(false);

            $table->foreign('item_id')->references('id')->on('items');
        });

        // Sincronización inicial del stock
        try {
            $stockService = app(RestaurantStockService::class);
            $result = $stockService->syncAllItems();
            \Log::info("Stock inicial sincronizado: {$result['processed']} items procesados");
        } catch (\Exception $e) {
            \Log::warning("No se pudo sincronizar stock inicial: " . $e->getMessage());
            // No lanzamos excepción para no detener la migración
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('restaurant_stock_products');
    }
};
