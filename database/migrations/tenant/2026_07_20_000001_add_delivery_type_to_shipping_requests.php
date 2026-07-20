<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rediseño del módulo de envíos: soporte para DOS tipos de entrega.
 *
 *  - domicilio: motorizado propio → usa Google Maps (coordenadas + place).
 *  - agencia:   envío por agencia de transporte a provincia (ubigeo).
 *
 * Agrega el discriminador `delivery_type` y los campos de geolocalización
 * (solo se llenan en entregas a domicilio; quedan NULL para agencia).
 * Idempotente por columna para no chocar con shipping:install.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_requests')) {
            return;
        }

        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
            $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_requests', $c);

            if (!$has('delivery_type')) {
                $table->string('delivery_type', 20)->default('agencia')->after('order_id')->index();
            }
            if (!$has('latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('district_id');
            }
            if (!$has('longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
            if (!$has('google_place_id')) {
                $table->string('google_place_id', 255)->nullable()->after('longitude');
            }
            if (!$has('formatted_address')) {
                $table->string('formatted_address', 500)->nullable()->after('google_place_id');
            }
            if (!$has('google_maps_url')) {
                $table->string('google_maps_url', 500)->nullable()->after('formatted_address');
            }
            if (!$has('courier_name')) {
                $table->string('courier_name', 120)->nullable()->after('google_maps_url');
            }
            if (!$has('courier_phone')) {
                $table->string('courier_phone', 20)->nullable()->after('courier_name');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->table('shipping_requests', function (Blueprint $table) {
            foreach ([
                'delivery_type', 'latitude', 'longitude', 'google_place_id',
                'formatted_address', 'google_maps_url', 'courier_name', 'courier_phone',
            ] as $col) {
                if (Schema::connection('tenant')->hasColumn('shipping_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
