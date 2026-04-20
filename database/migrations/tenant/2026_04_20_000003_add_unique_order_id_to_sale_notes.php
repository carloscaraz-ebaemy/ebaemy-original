<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega índice UNIQUE en `sale_notes.order_id`.
 *
 * En MySQL, las filas con `order_id IS NULL` son tratadas como distintas
 * bajo un UNIQUE, así que el índice no afecta a las NV sin pedido.
 *
 * Defensa antes de aplicar: si existen `order_id` duplicados
 * (no-null con count > 1), la migración aborta y lista los duplicados
 * en el log `laravel.log` para inspección manual. Esto evita bloqueos
 * de deploy por datos históricos sin resolver.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('sale_notes')) return;
        if (!Schema::hasColumn('sale_notes', 'order_id')) return;

        $duplicates = DB::table('sale_notes')
            ->select('order_id', DB::raw('COUNT(*) as total'), DB::raw('GROUP_CONCAT(id) as sale_note_ids'))
            ->whereNotNull('order_id')
            ->groupBy('order_id')
            ->having('total', '>', 1)
            ->get();

        if ($duplicates->isNotEmpty()) {
            Log::warning('[migration] sale_notes.order_id duplicates detected — UNIQUE index skipped', [
                'count' => $duplicates->count(),
                'sample' => $duplicates->take(10)->map(function ($row) {
                    return [
                        'order_id' => $row->order_id,
                        'sale_notes_count' => $row->total,
                        'sale_note_ids' => $row->sale_note_ids,
                    ];
                })->all(),
                'remediation' => 'Resuelve duplicados manualmente (elimina o re-asigna order_id) y vuelve a correr esta migración.',
            ]);
            return;
        }

        if ($this->indexExists('sale_notes', 'sale_notes_order_id_unique')) {
            return;
        }

        Schema::table('sale_notes', function (Blueprint $table) {
            $table->unique('order_id', 'sale_notes_order_id_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sale_notes')) return;
        if (!$this->indexExists('sale_notes', 'sale_notes_order_id_unique')) return;

        Schema::table('sale_notes', function (Blueprint $table) {
            $table->dropUnique('sale_notes_order_id_unique');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?',
            [$index]
        );
        return !empty($rows);
    }
};
