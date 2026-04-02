<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('logistic_returns')) {
            Schema::connection('tenant')->create('logistic_returns', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('sale_note_id')->nullable()->comment('Nota de venta original');
                $table->unsignedBigInteger('warehouse_id')->comment('Almacén donde se recibe la devolución');
                $table->unsignedBigInteger('user_id')->comment('Usuario que registra la devolución');

                // Estado: PENDIENTE → RECIBIDO → PROCESADO
                $table->string('status', 20)->default('PENDIENTE')
                      ->comment('PENDIENTE | RECIBIDO | PROCESADO');

                // Motivo de la devolución
                $table->string('reason', 30)->nullable()
                      ->comment('DEFECTO | EQUIVOCADO | ARREPENTIMIENTO | DANADO_TRANSPORTE | OTRO');

                $table->string('courier_name', 150)->nullable()->comment('Courier que devuelve');
                $table->string('tracking_number', 100)->nullable()->comment('Guía de retorno');
                $table->text('notes')->nullable()->comment('Observaciones del almacenero');

                $table->timestamp('received_at')->nullable()->comment('Fecha/hora de recepción física');
                $table->timestamp('processed_at')->nullable()->comment('Fecha/hora de procesamiento en stock');

                $table->index('sale_note_id');
                $table->index('status');
                $table->timestamps();
            });
        }

        if (!Schema::connection('tenant')->hasTable('logistic_return_items')) {
            Schema::connection('tenant')->create('logistic_return_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('logistic_return_id');
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('warehouse_id');

                $table->decimal('quantity_returned', 10, 3)->comment('Cantidad devuelta por el cliente');
                $table->decimal('quantity_restocked', 10, 3)->default(0)->comment('Cantidad reingresada al stock (buena condición)');

                // BUENO = regresa a stock | DAÑADO = se da de baja
                $table->string('condition', 10)->default('BUENO')->comment('BUENO | DANADO | PARCIAL');

                $table->decimal('unit_price', 10, 2)->default(0)->comment('Precio unitario al momento de la venta');
                $table->text('notes')->nullable();

                $table->foreign('logistic_return_id')->references('id')->on('logistic_returns')->onDelete('cascade');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('logistic_return_items');
        Schema::connection('tenant')->dropIfExists('logistic_returns');
    }
};
