<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia propia del catálogo de oficinas de Shalom.
 *
 * El catálogo viene de shalom-api.lat, un intermediario que NO es Shalom: la
 * llave puede vencerse, borrarse o el servicio caerse. El formulario del
 * cliente lee SIEMPRE de esta tabla y nunca del API, así que si la llave
 * muere el cliente sigue eligiendo oficina con la última lista buena.
 *
 * Nada se borra: una oficina que deja de salir en el API queda con
 * `listed = false` y `missing_since`, para que los envíos viejos que la
 * apuntan sigan sabiendo cuál era.
 *
 * La llave vive en `configurations` (cifrada) para que el SuperAdmin pueda
 * cambiarla sin tocar el .env del servidor.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('shalom_agencies')) {
            Schema::create('shalom_agencies', function (Blueprint $table) {
                $table->unsignedInteger('ter_id')->primary();     // id de Shalom
                $table->string('name', 160);                      // lugar_over: el nombre del local
                $table->string('full_name', 255)->nullable();     // DEP / PROV / DIST / LOCAL
                $table->string('department', 80)->nullable();
                $table->string('province', 80)->nullable();
                $table->string('zone', 120)->nullable();
                $table->char('ubigeo', 6)->nullable()->index();   // INEI, = districts.id
                $table->string('address', 500)->nullable();
                $table->string('phone', 60)->nullable();
                $table->string('hours', 255)->nullable();
                $table->text('details')->nullable();
                $table->decimal('latitude', 11, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->boolean('receives')->default(true);       // destino=1 y habilitado_OS=1
                $table->boolean('home_delivery')->default(false); // ter_reparto_habilitado
                $table->boolean('listed')->default(true);         // salió en la última sincronización
                $table->timestamp('missing_since')->nullable();
                $table->json('raw')->nullable();
                $table->timestamps();
            });
        }

        $add = [
            'shalom_api_key'        => fn (Blueprint $t) => $t->text('shalom_api_key')->nullable(),
            'shalom_synced_at'      => fn (Blueprint $t) => $t->timestamp('shalom_synced_at')->nullable(),
            'shalom_last_error'     => fn (Blueprint $t) => $t->string('shalom_last_error', 500)->nullable(),
            'shalom_last_error_at'  => fn (Blueprint $t) => $t->timestamp('shalom_last_error_at')->nullable(),
        ];
        foreach ($add as $column => $def) {
            if (!Schema::hasColumn('configurations', $column)) {
                Schema::table('configurations', $def);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shalom_agencies');
        foreach (['shalom_api_key', 'shalom_synced_at', 'shalom_last_error', 'shalom_last_error_at'] as $column) {
            if (Schema::hasColumn('configurations', $column)) {
                Schema::table('configurations', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
