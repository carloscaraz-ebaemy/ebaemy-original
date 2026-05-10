<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flag por producto: si está en true, el marketplace y los listados
 * ignoran las imágenes individuales de las variantes y usan SIEMPRE la
 * imagen principal del producto padre. Útil cuando el seller no tiene
 * fotos por color (Rojo/Amarillo) y subió logos genéricos en las
 * variantes — preferimos ver la foto del producto principal en su
 * lugar.
 *
 * Default false: no cambia el comportamiento histórico.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('items')) return;
        if (Schema::hasColumn('items', 'use_parent_image_for_variants')) return;

        Schema::table('items', function (Blueprint $table) {
            $table->boolean('use_parent_image_for_variants')
                  ->default(false)
                  ->after('has_variants');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('items')) return;
        if (!Schema::hasColumn('items', 'use_parent_image_for_variants')) return;

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('use_parent_image_for_variants');
        });
    }
};
