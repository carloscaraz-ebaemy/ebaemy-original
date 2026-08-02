<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opciones de premio: el sorteo puede ofrecer varias alternativas (cada una
 * con su nombre y su foto) y el cliente elige cuál quiere al aceptar
 * participar.
 *
 * Es OPCIONAL: un sorteo sin opciones sigue funcionando como antes, con el
 * premio único de las columnas `prize_*` de `raffles`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('raffles')) {
            return;
        }

        if (!Schema::hasTable('raffle_prize_options')) {
            Schema::create('raffle_prize_options', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('raffle_id')->index();
                $table->string('name', 160);
                $table->string('description', 500)->nullable();
                $table->string('image', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Qué opción eligió cada participante (null = no eligió / sin opciones).
        if (Schema::hasTable('raffle_participants')
            && !Schema::hasColumn('raffle_participants', 'prize_option_id')) {
            Schema::table('raffle_participants', function (Blueprint $table) {
                $table->unsignedBigInteger('prize_option_id')->nullable()->after('phone')->index();
            });
        }

        // El ganador congela la opción elegida, para que la ficha siga siendo
        // válida aunque después se edite o borre la opción.
        if (Schema::hasTable('raffle_winners')
            && !Schema::hasColumn('raffle_winners', 'prize_option_name')) {
            Schema::table('raffle_winners', function (Blueprint $table) {
                $table->string('prize_option_name', 160)->nullable()->after('prize_image');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('raffle_prize_options');

        if (Schema::hasTable('raffle_participants') && Schema::hasColumn('raffle_participants', 'prize_option_id')) {
            Schema::table('raffle_participants', fn (Blueprint $t) => $t->dropColumn('prize_option_id'));
        }
        if (Schema::hasTable('raffle_winners') && Schema::hasColumn('raffle_winners', 'prize_option_name')) {
            Schema::table('raffle_winners', fn (Blueprint $t) => $t->dropColumn('prize_option_name'));
        }
    }
};
