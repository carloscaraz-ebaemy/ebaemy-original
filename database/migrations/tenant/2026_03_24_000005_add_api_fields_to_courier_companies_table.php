<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade campos de integración API a la tabla courier_companies.
 *
 * api_driver   — identificador del servicio: 'chazki', '99minutos', 'olva', 'manual'
 *                'manual' = sin integración API (ingreso manual del tracking)
 * api_key      — clave pública de la API del carrier (cifrada en .env si es posible)
 * api_secret   — clave privada / secret de la API
 * api_endpoint — URL base de la API (permite apuntar a sandbox o producción)
 * api_sandbox  — true = modo prueba (sandbox)
 * api_meta     — JSON para configuración extra (headers, account_id, etc.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('courier_companies')) return;

        Schema::table('courier_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('courier_companies', 'api_driver')) {
                $table->string('api_driver', 30)->default('manual')->after('sort_order');
                $table->string('api_key', 255)->nullable()->after('api_driver');
                $table->string('api_secret', 255)->nullable()->after('api_key');
                $table->string('api_endpoint', 255)->nullable()->after('api_secret');
                $table->boolean('api_sandbox')->default(false)->after('api_endpoint');
                $table->json('api_meta')->nullable()->after('api_sandbox');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('courier_companies')) return;

        Schema::table('courier_companies', function (Blueprint $table) {
            if (Schema::hasColumn('courier_companies', 'api_driver')) {
                $table->dropColumn(['api_driver', 'api_key', 'api_secret', 'api_endpoint', 'api_sandbox', 'api_meta']);
            }
        });
    }
};
