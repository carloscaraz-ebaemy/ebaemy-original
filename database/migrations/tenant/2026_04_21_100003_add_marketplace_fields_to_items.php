<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Habilita la publicación de items en el Marketplace central (ebaemy.com).
 * Mantiene los datos del marketplace a nivel de item sin tocar sale_unit_price
 * (mp_price anula si está seteado, si no se usa el precio base del tenant).
 */
class AddMarketplaceFieldsToItems extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'marketplace_publishable')) {
                $table->boolean('marketplace_publishable')->default(false)->after('apply_store')
                      ->comment('Autorizado por el tenant para publicarse en ebaemy.com/marketplace');
            }
            if (!Schema::hasColumn('items', 'mp_price')) {
                $table->decimal('mp_price', 12, 2)->nullable()->after('marketplace_publishable')
                      ->comment('Precio para marketplace (si null, se usa sale_unit_price)');
            }
            if (!Schema::hasColumn('items', 'mp_status')) {
                $table->enum('mp_status', ['pending', 'active', 'paused', 'rejected'])
                      ->default('active')
                      ->after('mp_price')
                      ->comment('Estado en marketplace — alineado con marketplace_listings.status');
            }
            if (!Schema::hasColumn('items', 'mp_notes')) {
                $table->text('mp_notes')->nullable()->after('mp_status')
                      ->comment('Descripción extendida / SEO específico para marketplace');
            }
            if (!Schema::hasColumn('items', 'mp_synced_at')) {
                $table->timestamp('mp_synced_at')->nullable()->after('mp_notes');
            }
        });
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'marketplace_publishable', 'mp_price', 'mp_status', 'mp_notes', 'mp_synced_at',
            ]);
        });
    }
}
