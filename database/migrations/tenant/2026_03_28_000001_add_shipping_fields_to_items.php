<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('items')) return;

        Schema::table('items', function (Blueprint $table) {
            if (!Schema::hasColumn('items', 'weight')) {
                $table->decimal('weight', 8, 2)->nullable()->after('stock_min')
                    ->comment('Peso en kg');
            }
            if (!Schema::hasColumn('items', 'length')) {
                $table->decimal('length', 8, 2)->nullable()->after('weight')
                    ->comment('Largo en cm');
            }
            if (!Schema::hasColumn('items', 'width')) {
                $table->decimal('width', 8, 2)->nullable()->after('length')
                    ->comment('Ancho en cm');
            }
            if (!Schema::hasColumn('items', 'height')) {
                $table->decimal('height', 8, 2)->nullable()->after('width')
                    ->comment('Alto en cm');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('items')) return;
        Schema::table('items', function (Blueprint $table) {
            foreach (['weight', 'length', 'width', 'height'] as $col) {
                if (Schema::hasColumn('items', $col)) $table->dropColumn($col);
            }
        });
    }
};
