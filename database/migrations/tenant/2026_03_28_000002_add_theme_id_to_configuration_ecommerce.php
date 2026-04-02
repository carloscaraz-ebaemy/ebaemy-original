<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega theme_id a configuration_ecommerce (BD tenant).
 * Este campo referencia la tabla themes del sistema.
 * Coexiste con theme_template para compatibilidad hacia atrás.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) {
            return;
        }

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (!Schema::hasColumn('configuration_ecommerce', 'theme_id')) {
                $table->unsignedBigInteger('theme_id')->nullable()->after('theme_template');
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'ecommerce_mode')) {
                $table->string('ecommerce_mode', 20)->default('general')->after('theme_id'); // general | nicho
            }
            if (!Schema::hasColumn('configuration_ecommerce', 'business_type')) {
                $table->string('business_type', 30)->nullable()->after('ecommerce_mode');    // ropa, tecnologia, etc.
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) {
            return;
        }

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            if (Schema::hasColumn('configuration_ecommerce', 'theme_id')) {
                $table->dropColumn('theme_id');
            }
            if (Schema::hasColumn('configuration_ecommerce', 'ecommerce_mode')) {
                $table->dropColumn('ecommerce_mode');
            }
            if (Schema::hasColumn('configuration_ecommerce', 'business_type')) {
                $table->dropColumn('business_type');
            }
        });
    }
};
