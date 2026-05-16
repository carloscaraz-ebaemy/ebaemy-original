<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interes del comprador por categoria oficial del marketplace.
 *
 * Recalculada por job diario RecalculateMarketplaceUserInterests.
 * Formula inicial:
 *   score = views_30d + (favorites * 5) + (cart_adds * 10) + (purchases * 30)
 * con decay exponencial por tiempo desde la accion.
 *
 * PK compuesta (user_id, category_id) — solo una fila por par.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('marketplace_user_interests')) return;

        Schema::create('marketplace_user_interests', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('category_id');
            $table->decimal('score', 10, 2)->default(0);
            $table->timestamp('last_recalculated_at')->useCurrent();

            $table->primary(['user_id', 'category_id'], 'mkt_int_pk');
            $table->index(['user_id', 'score'], 'mkt_int_user_score_idx');

            $table->foreign('user_id')
                  ->references('id')->on('marketplace_users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_user_interests');
    }
};
