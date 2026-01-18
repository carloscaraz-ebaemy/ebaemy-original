<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSuppliesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('supplies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->decimal('cost', 12, 2)->default(0);
            $table->string('unit_type_id'); // relación con cat_unit_types (tipo string)
            $table->decimal('waste_percentage', 5, 2)->default(0); // merma (porcentaje)
            $table->decimal('stock', 12, 4)->default(0); // stock
            $table->decimal('minimum_stock', 12, 4)->default(0); // stock mínimo
            $table->timestamps();

            // Foreign key
            $table->foreign('unit_type_id')->references('id')->on('cat_unit_types');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('supplies');
    }
}
