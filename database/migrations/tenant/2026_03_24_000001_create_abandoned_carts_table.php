<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carritos abandonados — persiste el carrito del cliente en BD.
 *
 * Permite:
 * - Recuperar el carrito en la siguiente sesión (mismo dispositivo o distinto)
 * - Analizar productos más frecuentes en carritos sin compra
 * - Campañas de recuperación (email/WhatsApp a carritos abandonados)
 *
 * TTL: 7 días. Un comando scheduler borra registros expirados.
 */
class CreateAbandonedCartsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('abandoned_carts')) return;

        Schema::create('abandoned_carts', function (Blueprint $table) {
            $table->id();

            // Identificación del carrito
            $table->string('session_token', 64)->index();  // token anónimo (localStorage)
            $table->unsignedBigInteger('user_id')->nullable(); // si el usuario está logueado

            // Contenido del carrito
            $table->json('items');            // array de {id, variant_id, qty, price, ...}
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->unsignedSmallInteger('item_count')->default(0);

            // Contacto del cliente (capturado progresivamente en checkout)
            $table->string('customer_email',  191)->nullable()->index();
            $table->string('customer_phone',   30)->nullable();
            $table->string('customer_name',   191)->nullable();

            // Control
            $table->timestamp('recovered_at')->nullable(); // se setea cuando el carrito se convierte en orden
            $table->timestamp('expires_at')->nullable();   // nullable → calculado al guardar (now+7days)
            $table->timestamps();

            $table->index(['session_token', 'recovered_at']);
            $table->index('expires_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('abandoned_carts');
    }
}
