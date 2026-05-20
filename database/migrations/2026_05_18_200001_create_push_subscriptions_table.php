<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripciones Web Push de compradores del marketplace (system DB).
 *
 * Una fila por dispositivo/navegador suscrito. marketplace_user_id es
 * nullable: permitimos suscripciones anónimas (antes de login) y luego
 * las asociamos al usuario cuando se loguea.
 *
 * endpoint es único — el navegador genera uno por dispositivo+sitio.
 */
return new class extends Migration {
    public function getConnection()
    {
        return 'system';
    }

    public function up(): void
    {
        if (Schema::connection('system')->hasTable('push_subscriptions')) {
            return;
        }

        Schema::connection('system')->create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_user_id')->nullable()->index()
                ->comment('FK marketplace_users — nullable para suscripciones anonimas');
            $table->text('endpoint')->comment('Endpoint del push service del navegador');
            $table->string('endpoint_hash', 64)->unique()
                ->comment('sha256(endpoint) para unique index — endpoint es muy largo para indexar directo');
            $table->string('public_key')->comment('Clave p256dh del cliente');
            $table->string('auth_token')->comment('Token auth del cliente');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable()->comment('Ultimo push enviado con exito');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('system')->dropIfExists('push_subscriptions');
    }
};
