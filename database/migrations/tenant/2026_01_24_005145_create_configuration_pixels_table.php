<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('configuration_pixels', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255)->nullable();
            $table->text('script')->nullable();
            $table->enum('position', ['head', 'body'])->default('head');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('configuration_pixels');
    }
};
