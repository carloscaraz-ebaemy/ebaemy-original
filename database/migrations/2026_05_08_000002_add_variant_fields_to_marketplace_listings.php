<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hot-fix de Fase 0 — agregar los 3 campos de variantes que quedaron
 * fuera en producción.
 *
 * Contexto: la migration `2026_05_08_000001_add_offer_fields_to_marketplace_listings`
 * fue extendida en el commit A2 (de723fa6) para incluir has_variants/
 * min_price/max_price además de los 4 campos de oferta originales. Pero
 * en algunos entornos Laravel ya había registrado la migration como
 * ejecutada con la versión inicial (solo 4 campos), por lo que la
 * extensión no se aplicó al re-correr `migrate`.
 *
 * Esta migration secundaria es idempotente con hasColumn — si las
 * columnas ya existen (entornos donde sí se reaplicó la 000001 con la
 * versión extendida), no hace nada. Sin riesgo en producción.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            if (!Schema::hasColumn('marketplace_listings', 'has_variants')) {
                $table->boolean('has_variants')->default(false)->after('discount_pct');
            }
            if (!Schema::hasColumn('marketplace_listings', 'min_price')) {
                $table->decimal('min_price', 10, 2)->nullable()->after('has_variants');
            }
            if (!Schema::hasColumn('marketplace_listings', 'max_price')) {
                $table->decimal('max_price', 10, 2)->nullable()->after('min_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_listings', function (Blueprint $table) {
            foreach (['max_price', 'min_price', 'has_variants'] as $col) {
                if (Schema::hasColumn('marketplace_listings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
