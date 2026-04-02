<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlashSalesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('flash_sales')) {
            Schema::create('flash_sales', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('flash_sale_items')) {
            Schema::create('flash_sale_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flash_sale_id');
                $table->unsignedBigInteger('item_id');
                $table->decimal('flash_price', 12, 4);   // precio especial de oferta
                $table->timestamps();

                $table->foreign('flash_sale_id')->references('id')->on('flash_sales')->onDelete('cascade');
                $table->unique(['flash_sale_id', 'item_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
    }
}
