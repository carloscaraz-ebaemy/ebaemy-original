<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega campos de theme, modo ecommerce y configuración a clients.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clients')) return;

        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'theme_id')) {
                $table->unsignedBigInteger('theme_id')->nullable()->after('plan_id');
            }
            if (!Schema::hasColumn('clients', 'ecommerce_mode_id')) {
                $table->unsignedBigInteger('ecommerce_mode_id')->nullable()->after('theme_id');
            }
            if (!Schema::hasColumn('clients', 'business_type_id')) {
                $table->unsignedBigInteger('business_type_id')->nullable()->after('ecommerce_mode_id');
            }
            if (!Schema::hasColumn('clients', 'theme_settings')) {
                $table->json('theme_settings')->nullable()->after('business_type_id');
            }
            if (!Schema::hasColumn('clients', 'timezone')) {
                $table->string('timezone', 40)->default('America/Lima')->after('theme_settings');
            }
            if (!Schema::hasColumn('clients', 'currency')) {
                $table->string('currency', 3)->default('PEN')->after('timezone');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('clients')) return;

        Schema::table('clients', function (Blueprint $table) {
            $cols = ['theme_id', 'ecommerce_mode_id', 'business_type_id', 'theme_settings', 'timezone', 'currency'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('clients', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
