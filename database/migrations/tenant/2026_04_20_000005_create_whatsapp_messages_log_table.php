<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log de mensajes WhatsApp enviados desde el sistema.
 *
 * Registra cada intento de envío (individual o parte de campaña) con
 * su resultado, driver usado, plantilla aplicada y mensaje de error.
 *
 * Útil para:
 *   - Dashboard de métricas (cuántos enviados/fallidos por día)
 *   - Diagnóstico (¿por qué no llegó el mensaje al cliente X?)
 *   - Auditoría (trazabilidad de comunicación automatizada)
 *
 * NOTA: Las campañas masivas siguen usando `whatsapp_offer_campaign_messages`
 * (más detallado). Esta tabla es para mensajes transaccionales individuales
 * (notificación de pedido, OTP, carrito abandonado, etc.).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('whatsapp_messages_log')) {
            return;
        }

        Schema::create('whatsapp_messages_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('phone', 20);
            $table->string('driver', 20);                // meta_cloud | qr_api | none
            $table->string('type', 30)->default('text'); // text | template | media
            $table->string('template_name', 100)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending'); // pending | sent | failed
            $table->string('source', 50)->nullable();    // order | abandoned_cart | otp | campaign | manual
            $table->unsignedInteger('source_id')->nullable(); // id del recurso origen (order_id, etc.)
            $table->text('error_message')->nullable();
            $table->string('external_id', 120)->nullable(); // id devuelto por el proveedor
            $table->unsignedInteger('user_id')->nullable(); // quién disparó el envío (si aplica)
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['driver', 'created_at']);
            $table->index(['source', 'source_id']);
            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages_log');
    }
};
