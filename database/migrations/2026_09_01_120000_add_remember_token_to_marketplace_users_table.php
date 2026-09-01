<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `marketplace_users` no tenía `remember_token` y el login del comprador
 * fallaba con 500.
 *
 * El modelo usa el trait Authenticatable, que declara `remember_token` como
 * el campo del token, y MarketplaceAuthController llama a
 * `login($user, true)` en los cinco caminos de acceso (magic link, código,
 * password, registro y checkout). Al recordar la sesión, el guard escribe
 * `update marketplace_users set remember_token = ...` y MySQL respondía
 * "Unknown column 'remember_token'".
 *
 * Se agrega la columna en vez de apagar el recordar: mantener la sesión del
 * comprador entre visitas es intencional en el marketplace, donde el acceso
 * es por magic link y volver a pedirlo en cada visita es justo la fricción
 * que ese diseño evita.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_users')) return;
        if (Schema::hasColumn('marketplace_users', 'remember_token')) return;

        Schema::table('marketplace_users', function (Blueprint $table) {
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_users')) return;
        if (!Schema::hasColumn('marketplace_users', 'remember_token')) return;

        Schema::table('marketplace_users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
