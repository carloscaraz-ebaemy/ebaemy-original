<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enlace Registro de Envíos → Guía de Remisión.
 *
 * El rótulo identifica el paquete; la guía sustenta el traslado. Son cosas
 * distintas y un envío puede tener una, la otra o ambas, así que el vínculo va
 * como columna NULLABLE y no como requisito.
 *
 * FK a `dispatches` con unsignedInteger porque es tabla legacy del ERP
 * (ver feedback_legacy_fk_types).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
            $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_requests', $c);

            if (!$has('dispatch_id')) {
                $table->unsignedInteger('dispatch_id')->nullable()->after('tracking_number');
                $table->index('dispatch_id');
            }
            // Se guarda el numero al emitir: la guia puede anularse o borrarse y
            // el envio tiene que poder seguir diciendo que numero se le puso.
            if (!$has('dispatch_number')) {
                $table->string('dispatch_number', 30)->nullable()->after('dispatch_id');
            }
            if (!$has('dispatch_generated_at')) {
                $table->dateTime('dispatch_generated_at')->nullable()->after('dispatch_number');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
            foreach (['dispatch_generated_at', 'dispatch_number', 'dispatch_id'] as $c) {
                if (Schema::connection('tenant')->hasColumn('shipping_requests', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
