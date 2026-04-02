<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla pivot plan ↔ feature.
 *
 * Define qué features incluye cada plan.
 * Un feature no presente en esta tabla para un plan = NO incluido.
 *
 * El campo `limit` es opcional para features metered:
 *   ej: plan "Básico" tiene feature 'logistic_module' con limit=500 envíos/mes.
 *   NULL en limit = ilimitado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_features')) return;

        Schema::create('plan_features', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('plan_id');
            $table->unsignedInteger('feature_id');
            $table->unsignedBigInteger('limit')->nullable(); // null = sin límite
            $table->json('meta')->nullable();                // config extra por plan+feature
            $table->timestamps();

            $table->unique(['plan_id', 'feature_id']);
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->foreign('feature_id')->references('id')->on('features')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
