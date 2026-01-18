<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        Schema::connection('tenant')->create('restaurant_table_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('main_table_id')->nullable(); // ✅ Usa foreignId en lugar de unsignedBigInteger
            $table->string('status')->default('open');
            $table->datetime('opening_date')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::connection('tenant')->dropIfExists('restaurant_table_groups');
    }
};
