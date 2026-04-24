<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega marketplace_category_id a items en cada tenant.
 *
 * Es la FK lógica al árbol oficial system.marketplace_categories que el
 * seller selecciona al activar publicación en marketplace. Sin constraint
 * cross-DB porque Hyn Tenancy no permite FKs entre system y tenant
 * (mismo patrón ya usado en items.marketplace_publishable / mp_status).
 *
 * Por qué NO agregamos un nuevo enum 'marketplace_status':
 *   La columna mp_status (enum: pending, active, paused, rejected) ya
 *   cubre el workflow de moderación. Reutilizamos eso en lugar de duplicar.
 *   Mapping conceptual:
 *     mp_status='pending' = pending_review
 *     mp_status='active'  = approved/visible
 *     mp_status='paused'  = hidden
 *     mp_status='rejected'= rejected
 *     (no hay 'draft' explícito — se infiere de marketplace_publishable=false)
 */
class AddMarketplaceCategoryIdToItems extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'marketplace_category_id')) {
                $table->unsignedBigInteger('marketplace_category_id')
                      ->nullable()
                      ->after('mp_synced_at')
                      ->index();
            }
        });
    }

    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'marketplace_category_id')) {
                $table->dropColumn('marketplace_category_id');
            }
        });
    }
}
