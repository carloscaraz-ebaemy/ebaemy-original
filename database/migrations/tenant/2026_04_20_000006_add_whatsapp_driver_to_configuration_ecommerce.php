<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega configuración de driver y preferencias de notificación
 * WhatsApp a `configuration_ecommerce`.
 *
 * whatsapp_driver:
 *   - 'meta_cloud' (oficial), 'qr_api' (gateway), 'none' (deshabilitado)
 *   - Si es NULL, WhatsAppDriverFactory auto-detecta por el orden de config.
 *
 * whatsapp_notifications_enabled (JSON):
 *   - { "order_created": true, "payment_verified": true,
 *       "order_dispatched": true, "order_delivered": true,
 *       "order_cancelled": true, "abandoned_cart": false,
 *       "admin_new_order": true }
 *   - Si una clave falta o es false → ese tipo de notificación no se envía.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'whatsapp_driver')) {
                $table->string('whatsapp_driver', 30)->nullable()->after('whatsapp_vendor_number');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'whatsapp_notifications_enabled')) {
                $table->json('whatsapp_notifications_enabled')->nullable()->after('whatsapp_driver');
            }
        });
    }

    public function down(): void
    {
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            foreach (['whatsapp_driver', 'whatsapp_notifications_enabled'] as $col) {
                if (Schema::hasColumn('configuration_ecommerce', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
