<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semáforo de prioridad por antigüedad: plazo máximo de atención en días
 * hábiles (por defecto 4) y si se excluyen los feriados nacionales del conteo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_settings')) {
            return;
        }
        Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
            $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_settings', $c);
            if (!$has('max_business_days')) {
                $table->unsignedTinyInteger('max_business_days')->default(4)->after('require_payment');
            }
            if (!$has('aging_skip_holidays')) {
                $table->boolean('aging_skip_holidays')->default(true)->after('max_business_days');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection('tenant')->hasTable('shipping_settings')) {
            return;
        }
        Schema::connection('tenant')->table('shipping_settings', function (Blueprint $table) {
            $has = fn ($c) => Schema::connection('tenant')->hasColumn('shipping_settings', $c);
            if ($has('aging_skip_holidays')) {
                $table->dropColumn('aging_skip_holidays');
            }
            if ($has('max_business_days')) {
                $table->dropColumn('max_business_days');
            }
        });
    }
};
