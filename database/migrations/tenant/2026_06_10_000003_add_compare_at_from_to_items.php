<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de INICIO de la oferta (la de fin es compare_at_until). Juntas forman la
 * "Duración oferta" estilo Saga. El descuento se muestra en la tienda mientras
 * hoy esté dentro de [compare_at_from, compare_at_until] (cualquiera puede ser null).
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items') && !Schema::hasColumn('items', 'compare_at_from')) {
            Schema::table('items', function (Blueprint $table) {
                $table->date('compare_at_from')->nullable()->after('compare_at_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'compare_at_from')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('compare_at_from');
            });
        }
    }
};
