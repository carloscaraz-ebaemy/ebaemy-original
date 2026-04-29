<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de envíos WhatsApp desde el SuperAdmin a los tenants.
 * Tabla en SYSTEM DB (no tenant). Diferente de tenant.whatsapp_messages_log
 * que registra envíos del tenant a sus clientes.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('system_whatsapp_logs')) {
            return;
        }

        Schema::create('system_whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_hostname_id')->nullable()
                ->comment('Hostname/tenant destino (NULL si es a número arbitrario)');
            $table->string('recipient_phone', 32)
                ->comment('Número normalizado al que se envió');
            $table->string('recipient_name', 180)->nullable();
            $table->text('message');
            $table->string('status', 20)->default('pending')
                ->comment('pending|sent|failed');
            $table->string('source', 60)->default('manual')
                ->comment('manual|tenant_notification|broadcast|system_event');
            $table->string('error_message', 500)->nullable();
            $table->unsignedInteger('admin_user_id')->nullable()
                ->comment('Admin SaaS que disparó el envío');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('tenant_hostname_id');
            $table->index('status');
            $table->index('source');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_whatsapp_logs');
    }
};
