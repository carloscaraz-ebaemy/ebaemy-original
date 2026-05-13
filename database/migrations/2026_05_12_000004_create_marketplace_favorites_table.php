<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favoritos del marketplace (wishlist).
 *
 * Modelo session-based: cualquier visitante anónimo puede marcar
 * favoritos usando la cookie de sesión Laravel. Si más adelante hay
 * cuenta de comprador, asociar user_id no rompe nada (la columna ya
 * existe y es nullable).
 *
 * Limpieza: registros con last_active < 30 días pueden purgarse vía
 * php artisan model:prune o cron. No bloqueante para v1.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_favorites')) return;

        Schema::create('marketplace_favorites', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 80)->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('listing_id');
            $table->timestamps();

            $table->foreign('listing_id')
                  ->references('id')->on('marketplace_listings')
                  ->onDelete('cascade');

            // Un mismo session/user no debería tener el mismo listing
            // dos veces. Usamos un índice único combinado.
            $table->unique(['session_id', 'listing_id'], 'mp_fav_unique_session');
            $table->unique(['user_id', 'listing_id'], 'mp_fav_unique_user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_favorites');
    }
};
