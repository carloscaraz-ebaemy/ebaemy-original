<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega FK a marketplace_categories en cada listing publicado.
 *
 * IMPORTANTE: NO eliminamos `category_name` (string legacy) en esta fase.
 * Los listings existentes seguirán filtrándose por string mientras se
 * migran progresivamente al modelo nuevo. La fase E del plan limpia
 * el string una vez >95% de listings tengan FK.
 */
class AddMarketplaceCategoryIdToMarketplaceListings extends Migration
{
    public function up()
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'marketplace_category_id')) {
                $table->unsignedBigInteger('marketplace_category_id')
                      ->nullable()
                      ->after('category_name')
                      ->index();
                $table->foreign('marketplace_category_id')
                      ->references('id')->on('marketplace_categories')
                      ->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_listings', 'marketplace_category_id')) {
                $table->dropForeign(['marketplace_category_id']);
                $table->dropColumn('marketplace_category_id');
            }
        });
    }
}
