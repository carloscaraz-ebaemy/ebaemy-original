<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant verificado — insignia de confianza en el marketplace central.
 *
 * - clients.is_verified: fuente de verdad, toggleable por admin del landlord
 * - clients.verified_at: timestamp de cuándo se verificó
 * - marketplace_listings.tenant_verified: cache denormalizado para queries
 *   rápidas en la vitrina (evita JOIN contra clients en cada request)
 */
class AddVerifiedToClientsAndMarketplaceListings extends Migration
{
    public function up()
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('locked_tenant')
                      ->comment('Tenant verificado por el landlord — muestra badge en marketplace central');
            }
            if (!Schema::hasColumn('clients', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('is_verified');
            }
            if (!Schema::hasColumn('clients', 'verified_note')) {
                $table->string('verified_note', 180)->nullable()->after('verified_at')
                      ->comment('Nota interna del admin sobre la verificación');
            }
        });

        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'tenant_verified')) {
                $table->boolean('tenant_verified')->default(false)->after('tenant_logo_url')
                      ->index()
                      ->comment('Cache denormalizado de clients.is_verified');
            }
        });
    }

    public function down()
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            $table->dropColumn('tenant_verified');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['is_verified', 'verified_at', 'verified_note']);
        });
    }
}
