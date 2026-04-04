<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * 1. Agrega campo is_default a la tabla plans
 * 2. Migra todos los clientes de planes viejos al plan Enterprise
 * 3. Elimina los planes viejos
 * 4. Marca "Caserito" como plan por defecto
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Agregar campo is_default ─────────────────────────────────────
        if (!Schema::hasColumn('plans', 'is_default')) {
            Schema::table('plans', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('locked');
            });
        }

        // ── 2. Migrar clientes de planes viejos → Enterprise ────────────────
        $newPlanNames = ['Tienda Web', 'Caserito', 'Negocio', 'Pro', 'Enterprise'];

        $enterprise = DB::table('plans')->where('name', 'Enterprise')->first();

        if (!$enterprise) {
            throw new \RuntimeException('Plan Enterprise no encontrado. Ejecuta primero la migración seed_plans_with_features.');
        }

        $oldPlanIds = DB::table('plans')
            ->whereNotIn('name', $newPlanNames)
            ->pluck('id');

        if ($oldPlanIds->isNotEmpty()) {
            $affected = DB::table('clients')
                ->whereIn('plan_id', $oldPlanIds)
                ->update(['plan_id' => $enterprise->id]);

            if ($affected > 0) {
                echo "  → {$affected} clientes migrados al plan Enterprise (ID: {$enterprise->id})\n";
            }

            DB::table('plan_features')->whereIn('plan_id', $oldPlanIds)->delete();
            DB::table('plans')->whereIn('id', $oldPlanIds)->delete();

            echo "  → Planes viejos eliminados: " . $oldPlanIds->implode(', ') . "\n";
        }

        // ── 3. Marcar Caserito como plan por defecto ────────────────────────
        DB::table('plans')->update(['is_default' => false]);
        DB::table('plans')->where('name', 'Caserito')->update(['is_default' => true]);

        echo "  → Plan por defecto: Caserito\n";
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            if (Schema::hasColumn('plans', 'is_default')) {
                $table->dropColumn('is_default');
            }
        });

        echo "  ⚠ Los clientes migrados a Enterprise NO se revierten.\n";
    }
};
