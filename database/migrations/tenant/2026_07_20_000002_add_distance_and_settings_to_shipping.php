<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distancia tienda→cliente para entregas a domicilio (motorizado):
 *  - `shipping_settings`: guarda el ORIGEN (coordenadas de la tienda), 1 fila.
 *  - columnas de distancia en `shipping_requests` (km + textos de distancia/tiempo).
 *
 * Idempotente por columna/tabla para convivir con shipping:install.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tabla de configuración del módulo (origen de la tienda).
        if (!Schema::connection('tenant')->hasTable('shipping_settings')) {
            Schema::connection('tenant')->create('shipping_settings', function (Blueprint $table) {
                $table->id();
                $table->decimal('store_latitude', 10, 7)->nullable();
                $table->decimal('store_longitude', 10, 7)->nullable();
                $table->string('store_address', 500)->nullable();
                $table->timestamps();
            });
        }

        // Distancia calculada del envío (tienda → cliente).
        if (Schema::connection('tenant')->hasTable('shipping_requests')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_requests', $c);
                if (!$has('distance_km'))   $table->decimal('distance_km', 6, 2)->nullable()->after('google_maps_url');
                if (!$has('distance_text')) $table->string('distance_text', 40)->nullable()->after('distance_km');
                if (!$has('duration_text')) $table->string('duration_text', 40)->nullable()->after('distance_text');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('shipping_settings');
        if (Schema::connection('tenant')->hasTable('shipping_requests')) {
            Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
                foreach (['distance_km', 'distance_text', 'duration_text'] as $col) {
                    if (Schema::connection('tenant')->hasColumn('shipping_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
