<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapa guía de Shalom → tienda y envío.
 *
 * El webhook de shalom-api.lat es UNO por cuenta y llega al dominio central
 * con el N° de guía y su código: sin este mapa no hay forma de saber en qué
 * base de tenant está el envío. Se llena al guardar una guía con código
 * (subida a mano o creada por API) y se suscribe a sus cambios de estado.
 *
 * `shalom_webhook_secret` (cifrado): el secreto `whsec_…` con el que Shalom
 * firma cada aviso (cabecera X-Shalom-Signature, HMAC-SHA256).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('shalom_guides')) {
            Schema::create('shalom_guides', function (Blueprint $table) {
                $table->increments('id');
                $table->string('order_number', 12);
                $table->string('order_code', 12);
                $table->unsignedInteger('website_id');
                $table->unsignedInteger('shipping_request_id');
                $table->boolean('subscribed')->default(false);
                $table->string('last_status', 30)->nullable();
                $table->timestamp('last_event_at')->nullable();
                $table->timestamps();
                $table->unique(['order_number', 'order_code']);
                $table->index(['website_id', 'shipping_request_id']);
            });
        }

        if (!Schema::hasColumn('configurations', 'shalom_webhook_secret')) {
            Schema::table('configurations', function (Blueprint $t) {
                $t->text('shalom_webhook_secret')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shalom_guides');
        if (Schema::hasColumn('configurations', 'shalom_webhook_secret')) {
            Schema::table('configurations', fn (Blueprint $t) => $t->dropColumn('shalom_webhook_secret'));
        }
    }
};
