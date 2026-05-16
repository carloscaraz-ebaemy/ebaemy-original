<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preferencias de comunicacion del comprador.
 *
 * Una fila por user_id. Si no existe, defaults:
 *   email_frequency = 'weekly' (digest semanal)
 *   whatsapp_frequency = 'off' (opt-in explicito)
 *
 * Esta tabla cubre FRECUENCIA y CATEGORIAS suscritas. El consentimiento
 * legal (granted/revoked) vive aparte en marketplace_user_consents.
 *
 * Para enviar un email/whatsapp se requiere AMBOS:
 *   1. consent vigente para el (channel, purpose)
 *   2. frequency != 'off' en preferences
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_user_preferences')) return;

        Schema::create('marketplace_user_preferences', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->enum('email_frequency', ['off', 'daily', 'weekly', 'monthly'])
                  ->default('weekly');
            $table->enum('whatsapp_frequency', ['off', 'critical_only', 'weekly'])
                  ->default('off');
            // IDs de categorias del marketplace (oficial tree) que al user
            // le interesan explicitamente. Si esta vacio, derivamos del
            // comportamiento (vistos + favoritos + compras).
            $table->json('categories_subscribed')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                  ->references('id')->on('marketplace_users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_user_preferences');
    }
};
