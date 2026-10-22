<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Qué guías de novedades ya vio cada usuario.
 *
 * Las pantallas muestran un recorrido guiado («este botón sirve para…») la
 * primera vez que alguien entra después de una novedad. Se guarda en la cuenta
 * y no en el navegador para que no vuelva a salir al entrar desde el celular
 * después de haberla visto en la PC. Ver el componente FeatureTour.vue.
 *
 * `tour_key` lleva la novedad y su fecha («tienda-productos-2026-10»): una
 * novedad nueva en la misma pantalla es otra clave y vuelve a mostrarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('tenant')->hasTable('user_feature_tours')) {
            return;
        }

        Schema::connection('tenant')->create('user_feature_tours', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('tour_key', 80);
            // completed = la recorrió entera; dismissed = «No volver a mostrar».
            $table->string('status', 20)->default('completed');
            $table->timestamps();

            $table->unique(['user_id', 'tour_key']);
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('user_feature_tours');
    }
};
