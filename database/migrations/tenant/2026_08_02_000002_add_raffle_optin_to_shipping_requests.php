<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Intención de participar en el sorteo, declarada al registrar el envío.
 *
 * El cliente marca la casilla cuando su pedido acaba de nacer y todavía NO
 * tiene el pago confirmado, así que en ese momento no se le puede dar por
 * participante. Se guarda la intención y el sistema la convierte en
 * participación real recién cuando el encargado confirma el pago.
 *
 *   raffle_opt_in_id / _at → qué sorteo eligió y cuándo lo marcó
 *   raffle_joined_at       → cuándo se materializó (null = sigue pendiente)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shipping_requests')) {
            return;
        }

        Schema::table('shipping_requests', function (Blueprint $table) {
            $has = fn ($c) => Schema::hasColumn('shipping_requests', $c);

            if (!$has('raffle_opt_in_id')) {
                $table->unsignedBigInteger('raffle_opt_in_id')->nullable()->index()->after('accepted_terms');
            }
            if (!$has('raffle_opt_in_at')) {
                $table->dateTime('raffle_opt_in_at')->nullable()->after('raffle_opt_in_id');
            }
            if (!$has('raffle_joined_at')) {
                $table->dateTime('raffle_joined_at')->nullable()->after('raffle_opt_in_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('shipping_requests')) {
            return;
        }

        Schema::table('shipping_requests', function (Blueprint $table) {
            foreach (['raffle_opt_in_id', 'raffle_opt_in_at', 'raffle_joined_at'] as $c) {
                if (Schema::hasColumn('shipping_requests', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
