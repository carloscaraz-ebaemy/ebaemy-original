<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tag_template_fields', function( Blueprint $table) {
            $table->increments('id');
            $table->string('type', 100);
            $table->string('column');
            $table->string('x');
            $table->string('y');
            $table->string('width');
            $table->string('height');
            $table->json('style')->nullable();
            $table->json('barcode')->nullable();
            $table->string('image')->nullable();
            $table->string('html_id')->nullable();
            $table->boolean('has_image')->default(false);
            $table->unsignedInteger('tag_template_id');
            $table->foreign('tag_template_id')->references('id')->on('tag_templates')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tag_template_fields');
    }
};
