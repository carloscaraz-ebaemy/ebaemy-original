<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan contratado en shalom-api.lat. El API no lo informa (`/validate` solo
 * da cuota), así que lo declara el SuperAdmin y de él salen las capacidades:
 * básico = agencias, cotización y tracking; pro = + 1 instancia y webhooks;
 * business = instancias ilimitadas. Ver App\Services\System\ShalomPlan.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('configurations', 'shalom_plan')) {
            Schema::table('configurations', function (Blueprint $t) {
                $t->string('shalom_plan', 20)->nullable()->default('basico');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('configurations', 'shalom_plan')) {
            Schema::table('configurations', fn (Blueprint $t) => $t->dropColumn('shalom_plan'));
        }
    }
};
