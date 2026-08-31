<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estilo de animacion del sorteo, elegible por campaña.
 *
 * Solo afecta como se DIBUJA el resultado (ruleta o carrete). El ganador lo
 * sigue eligiendo el servidor dentro de la transaccion de
 * RaffleController@draw, asi que este valor no toca el azar.
 *
 * Idempotente: se re-corre sin romper (ver RafflesInstall).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('raffles')) {
            return;
        }

        if (Schema::connection('tenant')->hasColumn('raffles', 'draw_animation')) {
            return;
        }

        Schema::connection('tenant')->table('raffles', function (Blueprint $table) {
            $table->string('draw_animation', 20)->default('wheel')->after('status');
        });
    }

    public function down(): void
    {
        if (Schema::connection('tenant')->hasColumn('raffles', 'draw_animation')) {
            Schema::connection('tenant')->table('raffles', function (Blueprint $table) {
                $table->dropColumn('draw_animation');
            });
        }
    }
};
