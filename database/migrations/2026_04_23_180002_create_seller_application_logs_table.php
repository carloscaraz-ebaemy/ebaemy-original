<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría del workflow de solicitudes de sellers.
 *
 * Registra cada acción del SuperAdmin (cambio de estado, nota, solicitud
 * de documentos, aprobación, rechazo). Inmutable — no hay updated_at.
 */
class CreateSellerApplicationLogsTable extends Migration
{
    public function up()
    {
        Schema::create('seller_application_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('seller_application_id')->index();
            $table->string('action', 50)
                  ->comment('created, status_changed, note_added, docs_requested, approved, rejected');
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()
                  ->comment('SuperAdmin que ejecutó la acción — null si fue creada por el seller (action=created)');
            $table->timestamp('created_at')->nullable();

            $table->foreign('seller_application_id')
                  ->references('id')->on('seller_applications')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seller_application_logs');
    }
}
