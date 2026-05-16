<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprador del marketplace — entidad cross-tenant.
 *
 * Vive en system. Reconocido por ebaemy.com y todos los subdominios
 * via cookie de sesion cross-domain (SESSION_DOMAIN=.ebaemy.com).
 *
 * password_hash nullable: el registro inicial es por magic link;
 * el comprador puede setear password despues desde "Mi cuenta".
 *
 * email_verified_at se setea cuando consume el magic link.
 * status=deleted preserva la fila (consent legal) y bloquea login/envios.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_users')) return;

        Schema::create('marketplace_users', function (Blueprint $table) {
            $table->id();
            // utf8mb4_unicode_ci es case-insensitive por defecto en MySQL,
            // equivale a citext de Postgres para el caso login-by-email.
            $table->string('email', 190)->unique()->collation('utf8mb4_unicode_ci');
            $table->string('name', 120);
            $table->string('phone', 20)->nullable()->comment('E.164 sin +');
            $table->string('password_hash', 255)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('locale', 8)->default('es');
            $table->string('timezone', 64)->default('America/Lima');
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active')->index();
            $table->timestamps();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_users');
    }
};
