<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Valores de atributos obligatorios POR PRODUCTO para Saga Falabella.
 *
 * Saga exige, según la categoría, atributos como Color/Talla/Material que no
 * cubren los campos estándar del item. Guardamos los valores capturados en el
 * editor de atributos como un mapa { nombre_atributo_saga: valor } para que el
 * builder los inyecte en el <Attributes> del ProductCreate/Update.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_products') && !Schema::hasColumn('marketplace_products', 'attributes')) {
            Schema::table('marketplace_products', function (Blueprint $table) {
                $table->json('attributes')->nullable()->after('external_data');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketplace_products') && Schema::hasColumn('marketplace_products', 'attributes')) {
            Schema::table('marketplace_products', function (Blueprint $table) {
                $table->dropColumn('attributes');
            });
        }
    }
};
