<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
   public function up()
{
   Schema::table('configuration_ecommerce', function (Blueprint $table) {
        // ESTA ES LA QUE FALTA (según tu imagen) y se creará ahora:
        if (!Schema::hasColumn('configuration_ecommerce', 'termino_conditions')) {
            $table->text('termino_conditions')->nullable();
        }

        // ESTAS YA EXISTEN (según tu imagen), el 'if' hará que el sistema las salte y no dé error:
        if (!Schema::hasColumn('configuration_ecommerce', 'politica_privacy')) {
            $table->text('politica_privacy')->nullable();
        }
        if (!Schema::hasColumn('configuration_ecommerce', 'cambios_devolucion')) {
            $table->text('cambios_devolucion')->nullable();
        }
        if (!Schema::hasColumn('configuration_ecommerce', 'politica_envio')) {
            $table->text('politica_envio')->nullable();
        }
    });
}

public function down()
{
    Schema::table('configuration_ecommerce', function (Blueprint $table) {
        // Solo intenta borrar si la columna existe
        if (Schema::hasColumn('configuration_ecommerce', 'termino_conditions')) {
            $table->dropColumn('termino_conditions');
        }
        if (Schema::hasColumn('configuration_ecommerce', 'politica_privacy')) {
            $table->text('politica_privacy')->nullable();
        }
        if (Schema::hasColumn('configuration_ecommerce', 'cambios_devolucion')) {
            $table->text('cambios_devolucion')->nullable();
        }
        if (Schema::hasColumn('configuration_ecommerce', 'politica_envio')) {
            $table->text('politica_envio')->nullable();
        }
    });
}
};
