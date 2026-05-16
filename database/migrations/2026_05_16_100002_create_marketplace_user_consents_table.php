<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentimientos del comprador — append-only, una fila por evento.
 *
 * Nunca se hace UPDATE: cada grant/revoke crea una fila nueva.
 * El estado actual de un (user_id, channel, purpose) es la ultima
 * fila ordenada por granted_at (o revoked_at si revoked_at IS NOT NULL).
 *
 * Cumplimiento: cada envio de email/whatsapp valida consent vigente
 * antes de disparar y guarda referencia al consent_id usado en el log.
 *
 * Revocacion: cuando llega un revoke, los jobs en cola que tengan ese
 * usuario como destinatario deben re-validar consent antes de enviar.
 * SLA: revocacion respetada en <5 min.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_user_consents')) return;

        Schema::create('marketplace_user_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->enum('channel', ['email', 'whatsapp', 'sms']);
            $table->enum('purpose', [
                'transactional',
                'marketing',
                'price_alerts',
                'abandoned_cart',
            ]);
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            // Origen del consent: 'registration', 'mi_cuenta', 'checkout',
            // 'admin', 'unsubscribe_link', etc. Auditoria.
            $table->string('source', 40);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('user_id')
                  ->references('id')->on('marketplace_users')
                  ->onDelete('cascade');

            // Lookup tipico: estado actual de (user, channel, purpose).
            $table->index(['user_id', 'channel', 'purpose', 'id'], 'mkt_consent_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_user_consents');
    }
};
