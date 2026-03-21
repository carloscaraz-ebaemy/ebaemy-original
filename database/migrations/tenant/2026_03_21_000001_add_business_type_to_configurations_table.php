<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega business_type a configurations para soporte multirubro real.
 *
 * Valores posibles (BusinessTypeEnum):
 *   retail       → Retail / Tienda física
 *   restaurant   → Restaurante / POS
 *   ecommerce    → Tienda online
 *   services     → Servicios / Técnico
 *   logistics    → Operador logístico
 *   education    → Centro educativo
 *
 * Default: 'retail' — no rompe empresas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->string('business_type', 30)
                  ->default('retail')
                  ->after('id')
                  ->comment('Rubro del negocio. Ver BusinessTypeEnum.');
        });
    }

    public function down(): void
    {
        Schema::table('configurations', function (Blueprint $table) {
            $table->dropColumn('business_type');
        });
    }
};
