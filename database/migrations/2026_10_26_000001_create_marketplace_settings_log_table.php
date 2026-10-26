<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de cambios de la configuración de publicidad del marketplace
 * (píxeles, tokens de las APIs de conversiones, código de prueba, verificación
 * de dominio, interruptor de medición): quién, cuándo y qué.
 *
 * Los tokens NUNCA se guardan aquí en claro: `value` lleva la versión
 * enmascarada («•••• ZDZD · 207 caracteres»). Lo escribe
 * App\Services\Marketplace\SettingsAudit y se ve en /admin/marketplace/seo.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_settings_log')) {
            return;
        }
        Schema::create('marketplace_settings_log', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('user_name', 120)->nullable();
            $table->string('field', 80);
            $table->string('action', 20);            // set | changed | cleared
            $table->string('value', 160)->nullable(); // ya enmascarado si es secreto
            $table->string('note', 200)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['field', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_settings_log');
    }
};
