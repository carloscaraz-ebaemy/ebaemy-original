<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos de gestión de dominios a hostnames.
 * - is_primary: dominio principal del tenant
 * - redirect_to_primary: redirigir este hostname al dominio principal
 * - domain_type: subdomain | custom
 * - ssl_status: pending | active | expired | none
 * - ssl_expires_at: fecha de expiración del certificado
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('hostnames')) return;

        Schema::table('hostnames', function (Blueprint $table) {
            if (!Schema::hasColumn('hostnames', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('force_https');
            }
            if (!Schema::hasColumn('hostnames', 'redirect_to_primary')) {
                $table->boolean('redirect_to_primary')->default(false)->after('is_primary');
            }
            if (!Schema::hasColumn('hostnames', 'domain_type')) {
                $table->string('domain_type', 20)->default('subdomain')->after('redirect_to_primary');
            }
            if (!Schema::hasColumn('hostnames', 'ssl_status')) {
                $table->string('ssl_status', 20)->default('none')->after('domain_type');
            }
            if (!Schema::hasColumn('hostnames', 'ssl_expires_at')) {
                $table->timestamp('ssl_expires_at')->nullable()->after('ssl_status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('hostnames')) return;

        Schema::table('hostnames', function (Blueprint $table) {
            $cols = ['is_primary', 'redirect_to_primary', 'domain_type', 'ssl_status', 'ssl_expires_at'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('hostnames', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
