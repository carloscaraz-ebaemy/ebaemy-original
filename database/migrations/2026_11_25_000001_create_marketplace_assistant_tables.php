<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro del asistente de compras del marketplace (chat con Claude).
 *
 * Hasta ahora el bot no dejaba rastro: no se sabia que preguntaban, cuantas
 * busquedas daban cero, si hacian clic en las tarjetas ni cuanto costaba.
 * Tres tablas en la BD del sistema:
 *
 *  - conversations: una por navegador (session_key que guarda el cliente) con
 *    los contadores agregados y los tokens gastados.
 *  - messages: cada turno (comprador y bot) con las herramientas que uso, los
 *    productos que devolvio, tokens, latencia y el 👍/👎 del comprador.
 *  - events: lo que paso DESPUES de la respuesta: clic en tarjeta, agregado al
 *    carrito, salida a WhatsApp de la tienda. Es lo que mide si el bot vende.
 *
 * Idempotente: no toca una tabla que ya existe.
 */
return new class extends Migration
{
    protected $connection = 'system';

    public function up()
    {
        $schema = Schema::connection('system');

        if (!$schema->hasTable('marketplace_assistant_conversations')) {
            $schema->create('marketplace_assistant_conversations', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('session_key', 64)->unique();
                $t->unsignedBigInteger('buyer_id')->nullable()->index();
                $t->string('ip_hash', 64)->nullable()->index();
                $t->string('first_page', 500)->nullable();
                $t->string('first_message', 500)->nullable();
                $t->string('model', 80)->nullable();
                $t->unsignedInteger('messages_count')->default(0);
                $t->unsignedInteger('zero_results_count')->default(0);
                $t->unsignedInteger('products_count')->default(0);
                $t->unsignedInteger('clicks_count')->default(0);
                $t->unsignedInteger('cart_adds_count')->default(0);
                $t->unsignedInteger('handoffs_count')->default(0);
                $t->unsignedInteger('feedback_up')->default(0);
                $t->unsignedInteger('feedback_down')->default(0);
                $t->unsignedBigInteger('input_tokens')->default(0);
                $t->unsignedBigInteger('output_tokens')->default(0);
                $t->unsignedBigInteger('cache_read_tokens')->default(0);
                $t->timestamp('last_message_at')->nullable()->index();
                $t->timestamps();
            });
        }

        if (!$schema->hasTable('marketplace_assistant_messages')) {
            $schema->create('marketplace_assistant_messages', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('conversation_id')->index();
                $t->string('role', 12); // user | assistant
                $t->text('text')->nullable();
                $t->string('page_url', 500)->nullable();
                $t->string('context_slug', 250)->nullable();
                $t->json('tools')->nullable();        // [{name, input, total}]
                $t->json('products')->nullable();     // slugs devueltos
                $t->json('suggestions')->nullable();  // respuestas rapidas propuestas
                $t->boolean('zero_results')->default(false);
                $t->unsignedInteger('input_tokens')->default(0);
                $t->unsignedInteger('output_tokens')->default(0);
                $t->unsignedInteger('cache_read_tokens')->default(0);
                $t->unsignedInteger('latency_ms')->nullable();
                $t->string('error', 120)->nullable();
                $t->tinyInteger('feedback')->nullable(); // 1 util, -1 no util
                $t->string('feedback_note', 300)->nullable();
                $t->timestamp('created_at')->nullable()->index();
            });
        }

        if (!$schema->hasTable('marketplace_assistant_events')) {
            $schema->create('marketplace_assistant_events', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('conversation_id')->index();
                $t->unsignedBigInteger('message_id')->nullable();
                $t->string('type', 30)->index(); // click | cart | handoff | open | search_rescue
                $t->string('slug', 250)->nullable();
                $t->timestamp('created_at')->nullable()->index();
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection('system');
        $schema->dropIfExists('marketplace_assistant_events');
        $schema->dropIfExists('marketplace_assistant_messages');
        $schema->dropIfExists('marketplace_assistant_conversations');
    }
};
