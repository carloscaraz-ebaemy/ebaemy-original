<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de seller en el cliente (tenant).
 *
 * Complementa las columnas `is_verified` / `verified_at` existentes.
 * La semántica es:
 *
 *   - is_verified (existente):  el SuperAdmin confió en el tenant y lo
 *     marca como "verificado" → badge en marketplace.
 *   - marketplace_enabled:       toggle que el SuperAdmin puede apagar
 *     temporalmente sin revocar la verificación.
 *   - seller_status:             estado operativo del seller.
 *   - marketplace_approved_at/by: momento exacto en que se aprobó la
 *     solicitud y qué SuperAdmin lo hizo.
 *
 * Los tenants creados antes de esta migración empiezan con:
 *   marketplace_enabled = false, seller_status = 'inactive'.
 * Los tenants ya verificados se pueden re-habilitar con un update masivo
 * posterior si se desea.
 */
class AddMarketplaceColumnsToClientsTable extends Migration
{
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('marketplace_enabled')->default(false)->after('is_verified');
            $table->enum('seller_status', ['inactive', 'pending', 'active', 'suspended'])
                  ->default('inactive')
                  ->after('marketplace_enabled');
            $table->timestamp('marketplace_approved_at')->nullable()->after('seller_status');
            $table->unsignedBigInteger('marketplace_approved_by')->nullable()->after('marketplace_approved_at')
                  ->comment('FK a system.users (SuperAdmin que aprobó la solicitud del seller)');
        });
    }

    public function down()
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'marketplace_enabled',
                'seller_status',
                'marketplace_approved_at',
                'marketplace_approved_by',
            ]);
        });
    }
}
