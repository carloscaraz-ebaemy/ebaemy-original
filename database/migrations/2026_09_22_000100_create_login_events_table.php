<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitacora de autenticacion del sistema (superadmin, guard admin).
 *
 * La escribe App\Listeners\Security\RecordLoginEvent a partir de los eventos
 * de Illuminate\Auth. El agente de seguridad SOLO la lee.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('login_events')) return;

        Schema::create('login_events', function (Blueprint $table) {
            $table->id();
            // users.id es int en el esquema legacy: unsignedInteger, sin foreignId().
            $table->unsignedInteger('user_id')->nullable();
            $table->string('guard', 40)->nullable();
            $table->string('email', 191)->nullable()->comment('Identificador intentado, tambien en los fallos');
            $table->enum('result', ['success', 'failed', 'lockout', 'logout']);
            $table->string('ip_address', 45)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 6)->nullable();
            $table->decimal('longitude', 10, 6)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('url', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['email', 'result', 'created_at'], 'idx_login_email_result');
            $table->index(['ip_address', 'created_at'], 'idx_login_ip_date');
            $table->index(['user_id', 'created_at'], 'idx_login_user_date');
            $table->index('created_at', 'idx_login_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_events');
    }
};
