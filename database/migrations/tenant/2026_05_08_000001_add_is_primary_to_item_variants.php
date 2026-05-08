<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marca UNA variante como "principal" — la imagen que se muestra en la card
 * del marketplace por defecto, antes de cualquier hover. Estilo Falabella:
 * la imagen del listado coincide con el color seleccionado, y los dots
 * resaltan al activo. Sin esto, todas las cards muestran la imagen del
 * producto padre aunque la primera variante con foto sea de otro color.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('item_variants')) return;
        if (Schema::hasColumn('item_variants', 'is_primary')) return;

        Schema::table('item_variants', function (Blueprint $table) {
            $table->boolean('is_primary')->default(false)->after('is_active');
            $table->index(['item_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('item_variants')) return;
        if (!Schema::hasColumn('item_variants', 'is_primary')) return;

        Schema::table('item_variants', function (Blueprint $table) {
            $table->dropIndex(['item_id', 'is_primary']);
            $table->dropColumn('is_primary');
        });
    }
};
