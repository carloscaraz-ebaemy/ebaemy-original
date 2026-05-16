<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Magic links + codigos de verificacion (login passwordless).
 *
 * Flujo:
 *  1. Usuario pide login → generamos token opaco (link) + codigo 6 digitos.
 *     Solo el HASH del token y del codigo se persisten (nunca cleartext).
 *  2. Email con ambos: link directo (1-tap desktop) + codigo (mobile,
 *     porque el link puede abrir en otro browser sin sesion).
 *  3. Verify: usuario consume el link OR ingresa el codigo. Single-use.
 *
 * Rate limiting (en service, no en BD): 3 magic links/hora por email,
 * 10/hora por IP. TTL del token: 15 min.
 *
 * Si el user no existe al pedir magic link, se crea automaticamente
 * (registro pasivo). Email se considera verified al primer consume.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_user_magic_links')) return;

        Schema::create('marketplace_user_magic_links', function (Blueprint $table) {
            $table->id();
            // No hay FK a marketplace_users porque cuando se solicita
            // un link, el user puede no existir aun (se crea al verify).
            // En su lugar guardamos el email solicitado.
            $table->string('email', 190)->index();
            // SHA-256 hash del token aleatorio (32 bytes hex).
            $table->string('token_hash', 64)->unique();
            // SHA-256 hash del codigo de 6 digitos (cleartext nunca persiste).
            $table->string('code_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->timestamp('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0)
                  ->comment('Intentos fallidos de codigo; >=5 invalida');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_user_magic_links');
    }
};
