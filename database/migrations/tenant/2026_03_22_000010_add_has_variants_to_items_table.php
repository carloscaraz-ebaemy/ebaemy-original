<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega has_variants a la tabla items.
 * Flag opcional: si false, el producto funciona exactamente igual que antes.
 * No modifica ningún campo existente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('items', 'has_variants')) {
            Schema::table('items', function (Blueprint $table) {
                $table->boolean('has_variants')->default(false)->after('active')
                    ->comment('Si true, el stock y precio se gestionan por variante');
            });
        }
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('has_variants');
        });
    }
};
