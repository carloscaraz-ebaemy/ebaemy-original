<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha "válido hasta" del precio de oferta. El descuento (compare_at_price
 * tachado vs sale_unit_price) se muestra en la tienda mientras hoy <= esta fecha
 * (o si es null = sin vencimiento). Se importa desde Saga (SpecialToDate) y es
 * editable a mano en el producto.
 *
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('items') && !Schema::hasColumn('items', 'compare_at_until')) {
            Schema::table('items', function (Blueprint $table) {
                $table->date('compare_at_until')->nullable()->after('compare_at_price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('items') && Schema::hasColumn('items', 'compare_at_until')) {
            Schema::table('items', function (Blueprint $table) {
                $table->dropColumn('compare_at_until');
            });
        }
    }
};
