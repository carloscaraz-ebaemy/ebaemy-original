<?php

use Illuminate\Database\Migrations\Migration;
use App\Services\Tenant\CourierCompanyCatalog;

/**
 * Carga el catálogo extendido de empresas de transporte / courier en Perú.
 * Idempotente: solo inserta las que no estén ya registradas (match por nombre).
 *
 * Para tenants existentes correr también: php artisan couriers:seed-tenants
 */
return new class extends Migration
{
    public function up(): void
    {
        CourierCompanyCatalog::apply('tenant');
    }

    public function down(): void
    {
        // No-op: no removemos couriers existentes para no romper FKs en
        // sale_notes / shipping_guides que ya los referencian.
    }
};
