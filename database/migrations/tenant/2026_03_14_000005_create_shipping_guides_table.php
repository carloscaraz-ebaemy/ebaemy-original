<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guías de remisión para despachos a provincia.
 * Vincula con el módulo de Dispatch existente (dispatch_id) y almacena el PDF generado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistic_shipping_guides', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('logistic_order_id')->index();
            $table->unsignedInteger('dispatch_id')->nullable()->index()
                  ->comment('ID del dispatch generado en el módulo Dispatch existente');

            // Datos del transportista
            $table->string('carrier_name', 150)->nullable();
            $table->string('carrier_ruc', 15)->nullable();
            $table->string('carrier_plate', 20)->nullable();
            $table->string('driver_name', 150)->nullable();
            $table->string('driver_license', 30)->nullable();

            // Destino
            $table->string('origin_address')->nullable();
            $table->string('destination_address')->nullable();
            $table->string('destination_ubigeo', 10)->nullable();

            // Documento generado
            $table->string('series', 10)->nullable();
            $table->string('number', 20)->nullable();
            $table->string('pdf_path')->nullable()
                  ->comment('Ruta relativa en storage/tenant/{uuid}/shipping_guides/');

            // Tracking
            $table->string('tracking_code', 100)->nullable();
            $table->date('dispatch_date')->nullable();

            $table->string('status', 20)->default('generated')
                  ->comment('generated | sent_sunat | accepted | rejected');

            $table->unsignedInteger('issued_by')->nullable()
                  ->comment('user_id del almacenero que emitió');

            $table->timestamps();

            $table->foreign('logistic_order_id')
                  ->references('id')->on('logistic_orders')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistic_shipping_guides');
    }
};
