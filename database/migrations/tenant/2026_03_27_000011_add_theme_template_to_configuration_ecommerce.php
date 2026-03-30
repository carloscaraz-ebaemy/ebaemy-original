<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;
        if (Schema::hasColumn('configuration_ecommerce', 'theme_template')) return;

        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->string('theme_template', 30)->default('generic')->after('color_ecommerce');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('configuration_ecommerce')) return;
        if (!Schema::hasColumn('configuration_ecommerce', 'theme_template')) return;
        Schema::table('configuration_ecommerce', function (Blueprint $table) {
            $table->dropColumn('theme_template');
        });
    }
};
