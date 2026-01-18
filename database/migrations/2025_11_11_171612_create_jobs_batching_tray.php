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
        Schema::create('job_batching_trays', function (Blueprint $table) {
            $table->id();
            $table->string('job_batch_id');
            $table->foreign('job_batch_id')->references('id')->on('job_batches');
            $table->string('generated_filename');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('jobs_batching_tray');
    }
};
