<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega soporte para packs/conjuntos en marketplace_listings.
 *
 * Un item con `is_set=true` en el tenant es un bundle/pack: agrupa varios
 * items individuales con cantidades. Antes, el sync los trataba como items
 * normales y no había forma de mostrarlos como packs en el marketplace
 * público.
 *
 *   is_pack          bool flag para que la UI sepa que es un combo.
 *   pack_contents    JSON con [{item_id, name, quantity, image_url}] —
 *                    desnormalizado al sync, ya que la BD system no puede
 *                    joinar contra item_sets del tenant.
 *   pack_stock       stock máximo que se puede armar = min(item.stock / qty_in_pack).
 *                    Lo precomputamos al sync para no tener que recorrer
 *                    componentes en cada render del marketplace.
 *
 * Idempotente (hasColumn).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('marketplace_listings')) return;

        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'is_pack')) {
                $table->boolean('is_pack')->default(false)->after('has_variants')
                      ->comment('true = item es un bundle/conjunto en el tenant (item.is_set)');
            }
            if (!Schema::hasColumn('marketplace_listings', 'pack_contents')) {
                $table->json('pack_contents')->nullable()->after('is_pack')
                      ->comment('JSON [{item_id, name, quantity, image_url}] de los componentes del pack');
            }
            if (!Schema::hasColumn('marketplace_listings', 'pack_stock')) {
                $table->integer('pack_stock')->nullable()->after('pack_contents')
                      ->comment('Stock max del pack precomputado: min(componente.stock / qty)');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('marketplace_listings')) return;

        Schema::table('marketplace_listings', function (Blueprint $table) {
            foreach (['pack_stock', 'pack_contents', 'is_pack'] as $col) {
                if (Schema::hasColumn('marketplace_listings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
