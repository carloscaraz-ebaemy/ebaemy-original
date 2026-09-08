<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación del cobro, por PAGO y no por pedido.
 *
 * ── Por qué por pago ──────────────────────────────────────────────────────
 *
 * Hoy lo más parecido que existe es `shipping_requests.payment_confirmed`, un
 * booleano del ENVÍO entero. Con dos cobros parciales no se puede decir que uno
 * está verificado y el otro no: o el envío está confirmado o no lo está. Y en
 * Pedidos no existe ni siquiera eso.
 *
 * Registrar un cobro y comprobar que ese cobro es válido son dos hechos
 * distintos, los hacen dos personas distintas y pueden estar en momentos
 * distintos. Por eso el estado vive en la fila del pago.
 *
 * ── Nullable a propósito ──────────────────────────────────────────────────
 *
 * NULL significa «este cobro se registró antes de que existiera la
 * verificación», no «pendiente». Poner 'pendiente' por defecto convertiría los
 * 60 cobros ya registrados en trabajo atrasado inventado de la nada; poner
 * 'verificado' afirmaría que alguien los revisó, y nadie lo hizo.
 *
 * Los cobros NUEVOS arrancan en 'pendiente' solo si el tenant lo pide
 * (`require_payment_verification`), igual que `require_payment` gobierna hoy si
 * hace falta cobrar antes de rotular.
 *
 * ── Las dos tablas ────────────────────────────────────────────────────────
 *
 * `order_payments` y `shipping_payments` reciben lo mismo. Son los dos sitios
 * donde entra dinero y el operador tiene que poder responder lo mismo en los
 * dos; dejar una fuera obligaría a preguntar distinto según de dónde venga el
 * pedido, que es justo lo que este trabajo viene a quitar.
 *
 * De paso, `order_payments` gana `created_by`: `shipping_payments` ya guarda
 * quién registró el cobro y el de pedidos no, así que un cobro mal cargado no
 * tenía autor.
 *
 * Idempotente. No toca ninguna fila existente.
 */
class AddPaymentVerification extends Migration
{
    /** Las dos tablas donde entra dinero. */
    private const TABLAS = ['order_payments', 'shipping_payments'];

    public function up()
    {
        $schema = Schema::connection('tenant');

        foreach (self::TABLAS as $tabla) {
            if (!$schema->hasTable($tabla)) {
                continue;
            }

            $schema->table($tabla, function (Blueprint $table) use ($schema, $tabla) {
                if (!$schema->hasColumn($tabla, 'verification_status')) {
                    // Cadena y no enum: un enum obliga a una migración para
                    // añadir un estado, y el vocabulario lo cierra el modelo.
                    $table->string('verification_status', 20)->nullable();
                }

                if (!$schema->hasColumn($tabla, 'verified_by')) {
                    // `unsignedInteger` y sin FK: `users` es heredada con
                    // `int(10) unsigned` y un `foreignId()` no casa con ella.
                    $table->unsignedInteger('verified_by')->nullable();
                }

                if (!$schema->hasColumn($tabla, 'verified_at')) {
                    $table->timestamp('verified_at')->nullable();
                }

                if (!$schema->hasColumn($tabla, 'rejection_reason')) {
                    // Rechazar sin decir por qué deja al operador sin saber qué
                    // corregir, y al cliente sin respuesta.
                    $table->string('rejection_reason', 255)->nullable();
                }
            });
        }

        // Quién registró el cobro. `shipping_payments` ya lo tiene.
        if ($schema->hasTable('order_payments') && !$schema->hasColumn('order_payments', 'created_by')) {
            $schema->table('order_payments', function (Blueprint $table) {
                $table->unsignedInteger('created_by')->nullable();
            });
        }

        // El interruptor, junto a las otras dos reglas de cobro del tenant.
        if ($schema->hasTable('shipping_settings')
            && !$schema->hasColumn('shipping_settings', 'require_payment_verification')) {
            $schema->table('shipping_settings', function (Blueprint $table) {
                // Apagado por defecto: encenderlo cambia el trabajo diario de
                // quien registra cobros, y esa es una decisión de cada tienda.
                $table->boolean('require_payment_verification')->default(false);
            });
        }
    }

    public function down()
    {
        $schema = Schema::connection('tenant');

        foreach (self::TABLAS as $tabla) {
            if (!$schema->hasTable($tabla)) {
                continue;
            }

            $columnas = array_values(array_filter(
                ['verification_status', 'verified_by', 'verified_at', 'rejection_reason'],
                fn ($c) => $schema->hasColumn($tabla, $c)
            ));

            if ($columnas) {
                $schema->table($tabla, fn (Blueprint $t) => $t->dropColumn($columnas));
            }
        }

        if ($schema->hasTable('order_payments') && $schema->hasColumn('order_payments', 'created_by')) {
            $schema->table('order_payments', fn (Blueprint $t) => $t->dropColumn('created_by'));
        }

        if ($schema->hasTable('shipping_settings')
            && $schema->hasColumn('shipping_settings', 'require_payment_verification')) {
            $schema->table('shipping_settings', fn (Blueprint $t) => $t->dropColumn('require_payment_verification'));
        }
    }
}
