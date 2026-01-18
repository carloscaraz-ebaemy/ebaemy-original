<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TenantCreateTemplateColumnsConfigTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('template_columns_config', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('establishment_id');
            $table->string('template_name')->default('Plantilla_personalizable');
            $table->json('columns_config')->nullable();
            $table->timestamps();

            $table->foreign('establishment_id')->references('id')->on('establishments')->onDelete('cascade');
            $table->unique(['establishment_id', 'template_name']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('template_columns_config');
    }
}
